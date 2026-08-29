<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Http\Client\Response as HttpResponse;
use Pterodactyl\Services\HowToo\Ai\AiProviderPrompt;
use Pterodactyl\Services\HowToo\Ai\AiProviderAdapter;
use Pterodactyl\Services\HowToo\Ai\AiProviderException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;
use Pterodactyl\Services\HowToo\Ai\AiProviderCredential;
use Pterodactyl\Services\HowToo\Ai\AiAssistantProviderManager;

class AiAssistantProviderManagerTest extends TestCase
{
    public function testItFallsBackAcrossKeysAndThenProviders(): void
    {
        $repository = new FakeAiCredentialRepository(
            [
                ['name' => 'gemini', 'model' => 'gemini-test', 'priority' => 10],
                ['name' => 'groq', 'model' => 'groq-test', 'priority' => 20],
            ],
            [
                'gemini' => [
                    new AiProviderCredential('gemini', 'gemini-test', 'gemini-one', 1, false),
                    new AiProviderCredential('gemini', 'gemini-test', 'gemini-two', 2, false),
                ],
                'groq' => [
                    new AiProviderCredential('groq', 'groq-test', 'groq-one', 3, false),
                ],
            ],
        );
        $gemini = new FakeAiProviderAdapter('gemini', [
            'gemini-one' => new AiProviderException(AiProviderException::RATE_LIMIT, 429),
            'gemini-two' => new AiProviderException(AiProviderException::INVALID_CREDENTIAL, 401),
        ]);
        $groq = new FakeAiProviderAdapter('groq', ['groq-one' => 'Fallback answer']);
        $logger = new MemoryLogger();
        $manager = new AiAssistantProviderManager($repository, [$gemini, $groq], $logger);

        $result = $manager->generate(new AiProviderPrompt('System', [['role' => 'user', 'content' => 'Help']]));

        $this->assertSame('groq', $result->provider);
        $this->assertSame('Fallback answer', $result->answer);
        $this->assertSame(['gemini-one', 'gemini-two'], $gemini->attempts);
        $this->assertSame(['groq-one'], $groq->attempts);
        $this->assertSame([
            ['key_id' => 1, 'reason' => AiProviderException::RATE_LIMIT],
            ['key_id' => 2, 'reason' => AiProviderException::INVALID_CREDENTIAL],
        ], $repository->cooldowns);
        $this->assertSame([3], $repository->healthy);
        $this->assertFalse($logger->containsCredentialValue(['gemini-one', 'gemini-two', 'groq-one']));
    }

    public function testItReturnsAnErrorOnlyAfterEveryAvailableCredentialFails(): void
    {
        $repository = new FakeAiCredentialRepository(
            [['name' => 'gemini', 'model' => 'gemini-test', 'priority' => 10]],
            ['gemini' => [
                new AiProviderCredential('gemini', 'gemini-test', 'first', 1, false),
                new AiProviderCredential('gemini', 'gemini-test', 'second', 2, false),
            ]],
        );
        $adapter = new FakeAiProviderAdapter('gemini', [
            'first' => new AiProviderException(AiProviderException::TIMEOUT),
            'second' => new AiProviderException(AiProviderException::UNAVAILABLE, 503),
        ]);
        $manager = new AiAssistantProviderManager($repository, [$adapter], new MemoryLogger());

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('All configured providers failed or are cooling down.');

        try {
            $manager->generate(new AiProviderPrompt('System', [['role' => 'user', 'content' => 'Help']]));
        } finally {
            $this->assertSame(['first', 'second'], $adapter->attempts);
            $this->assertCount(2, $repository->cooldowns);
        }
    }

    public function testItClassifiesProviderFailuresAndAppliesBoundedCooldowns(): void
    {
        $rateLimit = AiProviderException::fromResponse(new HttpResponse(new PsrResponse(429, ['Retry-After' => '45'])));
        $invalid = AiProviderException::fromResponse(new HttpResponse(new PsrResponse(401)));
        $unavailable = AiProviderException::fromResponse(new HttpResponse(new PsrResponse(503)));

        $this->assertSame(AiProviderException::RATE_LIMIT, $rateLimit->reason);
        $this->assertSame(45, $rateLimit->cooldownSeconds());
        $this->assertSame(AiProviderException::INVALID_CREDENTIAL, $invalid->reason);
        $this->assertSame(1800, $invalid->cooldownSeconds());
        $this->assertSame(AiProviderException::UNAVAILABLE, $unavailable->reason);
        $this->assertSame(120, $unavailable->cooldownSeconds());
    }
}

final class FakeAiCredentialRepository implements AiCredentialRepository
{
    public array $cooldowns = [];
    public array $healthy = [];

    public function __construct(
        private array $providers,
        private array $credentials,
    ) {
    }

    public function orderedAiProviders(): array
    {
        return $this->providers;
    }

    public function availableAiCredentials(string $provider, string $model): array
    {
        return $this->credentials[$provider] ?? [];
    }

    public function putOnCooldown(AiProviderCredential $credential, int $seconds, string $reason): void
    {
        $this->cooldowns[] = ['key_id' => $credential->keyId, 'reason' => $reason];
    }

    public function markHealthy(AiProviderCredential $credential): void
    {
        $this->healthy[] = $credential->keyId;
    }
}

final class FakeAiProviderAdapter implements AiProviderAdapter
{
    public array $attempts = [];

    public function __construct(
        private string $provider,
        private array $responses,
    ) {
    }

    public function name(): string
    {
        return $this->provider;
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        $this->attempts[] = $credential->secret;
        $response = $this->responses[$credential->secret];
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }
}

final class MemoryLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = compact('level', 'message', 'context');
    }

    public function containsCredentialValue(array $credentials): bool
    {
        $logs = (string) json_encode($this->records);

        return collect($credentials)->contains(fn (string $credential): bool => str_contains($logs, $credential));
    }
}
