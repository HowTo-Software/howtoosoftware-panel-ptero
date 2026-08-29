<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\AiConversationShortcut;

class AiConversationShortcutTest extends TestCase
{
    public function testSimplePortugueseGreetingIsNaturalAndShort(): void
    {
        $response = (new AiConversationShortcut())->response('olá');

        $this->assertSame('Olá! Como posso ajudar?', $response);
        $this->assertLessThanOrEqual(30, mb_strlen($response));
    }

    public function testARealQuestionIsLeftForTheProvider(): void
    {
        $this->assertNull((new AiConversationShortcut())->response('olá, onde ficam os backups?'));
    }
}
