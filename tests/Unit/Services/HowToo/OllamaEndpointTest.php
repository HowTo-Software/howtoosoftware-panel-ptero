<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\Ai\OllamaEndpoint;

class OllamaEndpointTest extends TestCase
{
    public function testItNormalizesRootAndApiBaseUrls(): void
    {
        $this->assertSame('http://10.0.0.5:11435/api/chat', OllamaEndpoint::url('http://10.0.0.5:11435/', 'chat'));
        $this->assertSame('http://10.0.0.5:11435/api/tags', OllamaEndpoint::url('http://10.0.0.5:11435/api', '/api/tags'));
    }

    public function testItRejectsUrlsWithCredentialsOrUnexpectedPaths(): void
    {
        foreach (['ftp://host:11435', 'http://user@host:11435', 'http://user:pass@host:11435', 'http://host:11435/private'] as $url) {
            try {
                OllamaEndpoint::normalize($url);
                $this->fail("Expected $url to be rejected.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
