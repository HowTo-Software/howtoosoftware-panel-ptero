<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Psr\Http\Message\StreamInterface;

final class OllamaNdjsonStreamDecoder
{
    private const MAX_ANSWER_LENGTH = 12000;

    private string $buffer = '';
    private bool $done = false;

    public function consume(StreamInterface $stream, callable $onDelta): string
    {
        $answer = '';

        while (!$stream->eof() && !$this->done && mb_strlen($answer) < self::MAX_ANSWER_LENGTH) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                usleep(1000);
                continue;
            }

            foreach ($this->push($chunk) as $delta) {
                $this->append($answer, $delta, $onDelta);
            }
        }

        foreach ($this->finish() as $delta) {
            $this->append($answer, $delta, $onDelta);
        }

        return trim($answer);
    }

    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $lines = preg_split('/\r?\n/', $this->buffer) ?: [];
        $this->buffer = array_pop($lines) ?? '';

        return $this->decode($lines);
    }

    public function finish(): array
    {
        $line = trim($this->buffer);
        $this->buffer = '';

        return $line === '' ? [] : $this->decode([$line]);
    }

    private function decode(array $lines): array
    {
        $deltas = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $payload = json_decode($line, true);
            if (!is_array($payload)) {
                throw new AiProviderException(AiProviderException::INVALID_RESPONSE);
            }

            $error = trim((string) ($payload['error'] ?? ''));
            if ($error !== '') {
                throw new AiProviderException(AiProviderException::REQUEST_REJECTED);
            }

            $delta = data_get($payload, 'message.content');
            if (is_string($delta) && $delta !== '') {
                $deltas[] = $delta;
            }

            if (($payload['done'] ?? false) === true) {
                $this->done = true;
                break;
            }
        }

        return $deltas;
    }

    private function append(string &$answer, string $delta, callable $onDelta): void
    {
        $remaining = self::MAX_ANSWER_LENGTH - mb_strlen($answer);
        if ($remaining <= 0) {
            return;
        }

        $delta = mb_substr($delta, 0, $remaining);
        $answer .= $delta;
        $onDelta($delta);
    }
}
