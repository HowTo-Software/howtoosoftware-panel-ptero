<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\Ai\AiSseStreamDecoder;

class AiSseStreamDecoderTest extends TestCase
{
    public function testItDecodesFragmentedAndMultiLineSseEvents(): void
    {
        $decoder = new AiSseStreamDecoder();

        $this->assertSame([], $decoder->push("event: delta\ndata: {\"text\":\"Hel"));
        $this->assertSame(
            ['{"text":"Hello"}', '{"text":" world"}'],
            $decoder->push("lo\"}\n\nevent: delta\ndata: {\"text\":\" world\"}\n\n"),
        );
        $this->assertSame([], $decoder->finish());
    }
}
