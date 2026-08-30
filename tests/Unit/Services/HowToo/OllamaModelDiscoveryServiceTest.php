<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Services\HowToo\Ai\OllamaModelDiscoveryService;

class OllamaModelDiscoveryServiceTest extends TestCase
{
    private OllamaModelDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Http::swap(new Factory());
        $this->service = (new \ReflectionClass(OllamaModelDiscoveryService::class))->newInstanceWithoutConstructor();
    }

    public function testTagsReturnsSortedUniqueModelsWithBearerAuthentication(): void
    {
        Http::fake(['*ollama.test:11435/api/tags' => Http::response(['models' => [
            ['name' => 'qwen:latest'],
            ['model' => 'llama3.2:3b'],
            ['name' => 'qwen:latest'],
        ]])]);

        $result = $this->service->discover('http://ollama.test:11435/', 'model-secret');

        $this->assertTrue($result['connected']);
        $this->assertSame(['llama3.2:3b', 'qwen:latest'], $result['models']);
        Http::assertSent(fn ($request): bool => ($request->header('Authorization')[0] ?? null) === 'Bearer model-secret');
    }

    public function testTagsAuthenticationFailureIsSafe(): void
    {
        Http::fake(['*ollama.test:11435/api/tags' => Http::response([], 401)]);

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('rejected the configured API key');
        $this->service->discover('http://ollama.test:11435', 'secret-never-returned');
    }

    public function testConnectionRefusedReturnsAUsefulSafeError(): void
    {
        Http::fake(static fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('Could not connect to the Ollama server');
        $this->service->discover('http://ollama.test:11435', 'secret-never-returned');
    }
}
