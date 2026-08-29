<?php

namespace Pterodactyl\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Http\Middleware\VerifyCsrfToken;

class VerifyCsrfTokenTest extends TestCase
{
    public function testAssistantRouteIsNotExcludedFromCsrfProtection(): void
    {
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);
        $middleware = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $except = $property->getValue($middleware);

        $this->assertSame(['remote/*', 'daemon/*'], $except);
        $this->assertNotContains('api/client/servers/*/howtoo/assistant/stream', $except);
        $this->assertNotContains('*howtoo*', $except);
    }
}
