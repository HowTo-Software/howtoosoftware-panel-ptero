<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Psr\Http\Message\StreamInterface;

final class AiSseStreamDecoder
{
    private const MAX_ANSWER_LENGTH = 12000;

    private string $buffer = '';

    public function consume(StreamInterface $stream, callable $extractDelta, callable $onDelta): string
    {
        $answer = '';

        while (!$stream->eof() && mb_strlen($answer) < self::MAX_ANSWER_LENGTH) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                usleep(1000);
                continue;
            }

            foreach ($this->push($chunk) as $payload) {
                $this->append($answer, $extractDelta($payload), $onDelta);
                if (mb_strlen($answer) >= self::MAX_ANSWER_LENGTH) {
                    break 2;
                }
            }
        }

        foreach ($this->finish() as $payload) {
            $this->append($answer, $extractDelta($payload), $onDelta);
        }

        return trim($answer);
    }

    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $blocks = preg_split('/\r?\n\r?\n/', $this->buffer) ?: [];
        $this->buffer = array_pop($blocks) ?? '';

        return $this->payloads($blocks);
    }

    public function finish(): array
    {
        $block = trim($this->buffer);
        $this->buffer = '';

        return $block === '' ? [] : $this->payloads([$block]);
    }

    private function payloads(array $blocks): array
    {
        return collect($blocks)
            ->map(function (string $block): string {
                return collect(preg_split('/\r?\n/', $block) ?: [])
                    ->filter(fn (string $line): bool => str_starts_with($line, 'data:'))
                    ->map(fn (string $line): string => ltrim(substr($line, 5)))
                    ->implode("\n");
            })
            ->filter()
            ->values()
            ->all();
    }

    private function append(string &$answer, mixed $delta, callable $onDelta): void
    {
        if (!is_string($delta) || $delta === '') {
            return;
        }

        $remaining = self::MAX_ANSWER_LENGTH - mb_strlen($answer);
        if ($remaining <= 0) {
            return;
        }

        $delta = mb_substr($delta, 0, $remaining);
        $answer .= $delta;
        $onDelta($delta);
    }
}
