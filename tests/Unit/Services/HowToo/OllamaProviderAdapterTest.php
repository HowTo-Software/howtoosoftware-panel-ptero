<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Pterodactyl\Services\HowToo\Ai\AiProviderPrompt;
use Pterodactyl\Services\HowToo\Ai\AiProviderException;
use Pterodactyl\Services\HowToo\Ai\AiProviderCredential;
use Pterodactyl\Services\HowToo\Ai\OllamaProviderAdapter;

class OllamaProviderAdapterTest extends TestCase
{
    private OllamaProviderAdapter $adapter;
    private AiProviderCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        Http::swap(new Factory());
        $this->adapter = new OllamaProviderAdapter();
        $this->credential = new AiProviderCredential(
            'ollama',
            'qwen-test:latest',
            'private-test-key',
            1,
            false,
            90,
            'http://ollama.test:11435/',
        );
    }

    public function testNonStreamingChatUsesSelectedModelAndBearerToken(): void
    {
        Http::fake(['ollama.test:11435/api/chat' => Http::response([
            'message' => ['role' => 'assistant', 'content' => 'Local answer'],
            'done' => true,
        ])]);

        $answer = $this->adapter->generate($this->credential, $this->prompt());

        $this->assertSame('Local answer', $answer);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('Bearer private-test-key', $request->header('Authorization')[0] ?? null);
            $this->assertSame('qwen-test:latest', $request['model']);
            $this->assertFalse($request['stream']);
            $this->assertSame('System', $request['messages'][0]['content']);

            return true;
        });
    }

    public function testStreamingChatEmitsOllamaNdjsonDeltas(): void
    {
        Http::fake(['ollama.test:11435/api/chat' => Http::response(
            '{"message":{"content":"Local "},"done":false}' . "\n"
            . '{"message":{"content":"stream"},"done":false}' . "\n"
            . '{"done":true}' . "\n",
            200,
            ['Content-Type' => 'application/x-ndjson'],
        )]);
        $deltas = [];

        $answer = $this->adapter->stream($this->credential, $this->prompt(), static function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        });

        $this->assertSame('Local stream', $answer);
        $this->assertSame(['Local ', 'stream'], $deltas);
        Http::assertSent(fn (Request $request): bool => $request['stream'] === true);
    }

    #[DataProvider('providerErrorStatuses')]
    public function testItClassifiesProviderHttpErrors(int $status, string $reason): void
    {
        Http::fake(['ollama.test:11435/api/chat' => Http::response(['error' => 'failure'], $status)]);

        try {
            $this->adapter->generate($this->credential, $this->prompt());
            $this->fail('Expected provider failure.');
        } catch (AiProviderException $exception) {
            $this->assertSame($reason, $exception->reason);
            $this->assertSame($status, $exception->status);
        }
    }

    public static function providerErrorStatuses(): array
    {
        return [
            'unauthorized' => [401, AiProviderException::INVALID_CREDENTIAL],
            'forbidden' => [403, AiProviderException::INVALID_CREDENTIAL],
            'timeout' => [408, AiProviderException::TIMEOUT],
            'model missing' => [404, AiProviderException::MODEL_NOT_FOUND],
            'rate limited' => [429, AiProviderException::RATE_LIMIT],
            'server error' => [500, AiProviderException::UNAVAILABLE],
        ];
    }

    public function testEmptyAndMalformedResponsesAreRejected(): void
    {
        Http::fake(['ollama.test:11435/api/chat' => Http::response(['message' => ['content' => '']])]);
        try {
            $this->adapter->generate($this->credential, $this->prompt());
            $this->fail('Expected empty response rejection.');
        } catch (AiProviderException $exception) {
            $this->assertSame(AiProviderException::INVALID_RESPONSE, $exception->reason);
        }

        Http::fake(['ollama.test:11435/api/chat' => Http::response("malformed\n", 200, [
            'Content-Type' => 'application/x-ndjson',
        ])]);
        $this->expectException(AiProviderException::class);
        $this->adapter->stream($this->credential, $this->prompt(), static fn (): null => null);
    }

    private function prompt(): AiProviderPrompt
    {
        return new AiProviderPrompt('System', [['role' => 'user', 'content' => 'Help']]);
    }
}
