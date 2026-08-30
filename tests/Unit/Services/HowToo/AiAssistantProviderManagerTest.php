<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\HowToo\Ai\AiProviderPrompt;
use Pterodactyl\Services\HowToo\Ai\AiProviderAdapter;
use Pterodactyl\Services\HowToo\Ai\AiProviderException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;
use Pterodactyl\Services\HowToo\Ai\AiProviderCredential;
use Pterodactyl\Services\HowToo\Ai\AiAssistantProviderManager;
use Pterodactyl\Services\HowToo\Ai\StreamingAiProviderAdapter;

class AiAssistantProviderManagerTest extends TestCase
{
    public function testItUsesOnlyTheConfiguredOllamaProvider(): void
    {
        $repository = $this->repository();
        $adapter = new FakeOllamaAdapter(['ollama-secret' => 'Local answer']);

        $result = (new AiAssistantProviderManager($repository, [$adapter], new MemoryLogger()))
            ->generate($this->prompt());

        $this->assertSame('ollama', $result->provider);
        $this->assertSame('Local answer', $result->answer);
        $this->assertSame(['ollama-secret'], $adapter->attempts);
        $this->assertSame([1], $repository->healthy);
    }

    public function testClassifiedProviderFailureAppliesCooldownAndReturnsSafeError(): void
    {
        $repository = $this->repository();
        $adapter = new FakeOllamaAdapter([
            'ollama-secret' => new AiProviderException(AiProviderException::RATE_LIMIT, 429, 30),
        ]);

        try {
            (new AiAssistantProviderManager($repository, [$adapter], new MemoryLogger()))->generate($this->prompt());
            $this->fail('Expected provider error.');
        } catch (DisplayException $exception) {
            $this->assertStringContainsString('local AI assistant is busy', $exception->getMessage());
            $this->assertSame([['key_id' => 1, 'reason' => AiProviderException::RATE_LIMIT]], $repository->cooldowns);
        }
    }

    public function testUnexpectedPhpExceptionDoesNotPoisonCredentialOrLeakSecrets(): void
    {
        $repository = $this->repository();
        $logger = new MemoryLogger();
        $adapter = new FakeOllamaAdapter([
            'ollama-secret' => new \RuntimeException('failure at http://ollama.internal:11435 using ollama-secret'),
        ]);

        try {
            (new AiAssistantProviderManager($repository, [$adapter], $logger))->generate($this->prompt());
            $this->fail('Expected internal provider error.');
        } catch (DisplayException $exception) {
            $this->assertStringContainsString('internal error', $exception->getMessage());
            $this->assertSame([], $repository->cooldowns);
            $this->assertFalse($logger->contains(['ollama-secret', 'http://ollama.internal:11435']));
            $this->assertTrue($logger->contains(['[redacted]']));
        }
    }

    public function testNoAvailableCredentialReturnsConfiguredStateInsteadOfAttemptsZeroFailure(): void
    {
        $repository = new FakeOllamaCredentialRepository(
            [['name' => 'ollama', 'model' => 'qwen:latest', 'timeout_seconds' => 90]],
            [],
        );

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('not configured or is temporarily unavailable');
        (new AiAssistantProviderManager($repository, [new FakeOllamaAdapter([])], new MemoryLogger()))
            ->generate($this->prompt());
    }

    public function testStreamingUsesTheSameOllamaRequestWithoutFallbackReset(): void
    {
        $repository = $this->repository();
        $adapter = new FakeStreamingOllamaAdapter(['Local ', 'stream']);
        $answer = '';
        $resets = 0;

        $result = (new AiAssistantProviderManager($repository, [$adapter], new MemoryLogger()))->stream(
            $this->prompt(),
            static function (string $delta) use (&$answer): void {
                $answer .= $delta;
            },
            null,
            static function () use (&$resets): void {
                ++$resets;
            },
        );

        $this->assertSame('Local stream', $result->answer);
        $this->assertSame('Local stream', $answer);
        $this->assertSame(0, $resets);
    }

    public function testLongPromptIsCompactedAndReceivesOllamaTimeoutBudget(): void
    {
        $repository = $this->repository();
        $adapter = new FakeOllamaAdapter(['ollama-secret' => 'Answer']);
        $prompt = new AiProviderPrompt(str_repeat('system ', 3000), [
            ['role' => 'user', 'content' => str_repeat('question ', 2000)],
        ]);

        (new AiAssistantProviderManager($repository, [$adapter], new MemoryLogger(), 120))->generate($prompt);

        $this->assertLessThanOrEqual(20000, $adapter->promptLengths[0]);
        $this->assertGreaterThanOrEqual(89, $adapter->timeouts[0]);
    }

    private function repository(): FakeOllamaCredentialRepository
    {
        return new FakeOllamaCredentialRepository(
            [['name' => 'ollama', 'model' => 'qwen:latest', 'timeout_seconds' => 90]],
            [new AiProviderCredential(
                'ollama',
                'qwen:latest',
                'ollama-secret',
                1,
                false,
                90,
                'http://ollama.internal:11435',
            )],
        );
    }

    private function prompt(): AiProviderPrompt
    {
        return new AiProviderPrompt('System', [['role' => 'user', 'content' => 'Help']]);
    }
}

final class FakeOllamaCredentialRepository implements AiCredentialRepository
{
    public array $cooldowns = [];
    public array $healthy = [];

    public function __construct(private array $providers, private array $credentials)
    {
    }

    public function orderedAiProviders(): array
    {
        return $this->providers;
    }

    public function availableAiCredentials(string $provider, string $model, int $timeoutSeconds): array
    {
        return $this->credentials;
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

final class FakeOllamaAdapter implements AiProviderAdapter
{
    public array $attempts = [];
    public array $timeouts = [];
    public array $promptLengths = [];

    public function __construct(private array $responses)
    {
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        $this->attempts[] = $credential->secret;
        $this->timeouts[] = $credential->timeoutSeconds;
        $this->promptLengths[] = mb_strlen($prompt->system)
            + collect($prompt->messages)->sum(fn (array $message): int => mb_strlen($message['content']));
        $response = $this->responses[$credential->secret];
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }
}

final class FakeStreamingOllamaAdapter implements StreamingAiProviderAdapter
{
    public function __construct(private array $deltas)
    {
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function generate(AiProviderCredential $credential, AiProviderPrompt $prompt): string
    {
        return implode('', $this->deltas);
    }

    public function stream(AiProviderCredential $credential, AiProviderPrompt $prompt, callable $onDelta): string
    {
        foreach ($this->deltas as $delta) {
            $onDelta($delta);
        }

        return implode('', $this->deltas);
    }
}

final class MemoryLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = compact('level', 'message', 'context');
    }

    public function contains(array $values): bool
    {
        $logs = (string) json_encode($this->records);

        return collect($values)->contains(fn (string $value): bool => str_contains($logs, $value));
    }
}
