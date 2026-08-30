<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\Ai\AiProviderException;
use Pterodactyl\Services\HowToo\Ai\OllamaNdjsonStreamDecoder;
use Pterodactyl\Services\HowToo\Ai\AiStreamCancelledException;

class OllamaNdjsonStreamDecoderTest extends TestCase
{
    public function testItBuffersPartialJsonAndStopsAtDone(): void
    {
        $decoder = new OllamaNdjsonStreamDecoder();

        $this->assertSame([], $decoder->push('{"message":{"content":"Hel'));
        $this->assertSame(['Hello'], $decoder->push('lo"},"done":false}' . "\n"));
        $this->assertSame([' world'], $decoder->push(
            '{"message":{"content":" world"},"done":false}' . "\n"
            . '{"message":{"content":""},"done":true}' . "\n"
            . '{"message":{"content":"ignored"},"done":false}' . "\n",
        ));
    }

    public function testItConsumesMultipleNdjsonChunks(): void
    {
        $stream = Utils::streamFor(
            '{"message":{"content":"How"},"done":false}' . "\n"
            . '{"message":{"content":"Too"},"done":false}' . "\n"
            . '{"done":true}' . "\n",
        );
        $deltas = [];

        $answer = (new OllamaNdjsonStreamDecoder())->consume($stream, static function (string $delta) use (&$deltas): void {
            $deltas[] = $delta;
        });

        $this->assertSame('HowToo', $answer);
        $this->assertSame(['How', 'Too'], $deltas);
    }

    public function testMalformedJsonIsRejected(): void
    {
        $this->expectException(AiProviderException::class);

        (new OllamaNdjsonStreamDecoder())->push("not-json\n");
    }

    public function testClientCancellationStopsStreamProcessing(): void
    {
        $this->expectException(AiStreamCancelledException::class);

        (new OllamaNdjsonStreamDecoder())->consume(
            Utils::streamFor('{"message":{"content":"delta"},"done":false}' . "\n"),
            static fn () => throw new AiStreamCancelledException(),
        );
    }
}
