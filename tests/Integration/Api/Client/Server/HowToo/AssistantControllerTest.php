<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server\HowToo;

use Illuminate\Cookie\CookieValuePrefix;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class AssistantControllerTest extends ClientApiIntegrationTestCase
{
    public function testAuthenticatedSessionCanPostToStreamingEndpoint(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $token = 'known-csrf-token';
        $encrypter = $this->app->make('encrypter');
        $encryptedToken = $encrypter->encrypt(
            CookieValuePrefix::create('XSRF-TOKEN', $encrypter->getKey()) . $token,
            false,
        );

        $response = $this->actingAs($user)
            ->withSession(['_token' => $token])
            ->withCookie('XSRF-TOKEN', $encryptedToken)
            ->withHeaders([
                'Accept' => 'text/event-stream',
                'Content-Type' => 'application/json',
                'Referer' => 'http://localhost/server/' . $server->uuid,
                'X-Requested-With' => 'XMLHttpRequest',
                'X-XSRF-TOKEN' => $encryptedToken,
            ])
            ->postJson("/api/client/servers/{$server->uuid}/howtoo/assistant/stream", [
                'message' => 'hello',
                'history' => [],
                'section' => 'assistant',
                'server_status' => 'running',
            ]);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $stream = $response->streamedContent();
        $this->assertStringContainsString("event: status\n", $stream);
        $this->assertStringContainsString("event: delta\n", $stream);
        $this->assertStringContainsString("event: done\n", $stream);
    }
}
