<?php

namespace Pterodactyl\Services\HowToo\Ai;

final class AiPromptBudgeter
{
    private const MESSAGE_OVERHEAD = 32;
    private const OMISSION_MARKER = "\n[older contextual details omitted to stay within provider limits]\n";
    private const MESSAGE_OMISSION_MARKER = "\n[middle of this message omitted to stay within provider limits]\n";

    public function __construct(
        private int $inputCharacterBudget = 20000,
        private int $systemCharacterBudget = 12000,
    ) {
        $this->inputCharacterBudget = max(8000, min($this->inputCharacterBudget, 50000));
        $this->systemCharacterBudget = max(4000, min($this->systemCharacterBudget, $this->inputCharacterBudget - 3500));
    }

    public function compact(AiProviderPrompt $prompt): AiProviderPrompt
    {
        $system = $this->compactSystem($prompt->system);
        $messages = array_values(array_filter(
            $prompt->messages,
            static fn ($message): bool => is_array($message)
                && in_array($message['role'] ?? null, ['user', 'assistant'], true)
                && is_string($message['content'] ?? null)
                && trim($message['content']) !== '',
        ));

        if ($messages === []) {
            return new AiProviderPrompt($system, []);
        }

        $current = array_pop($messages);
        $current['content'] = $this->compactText(
            $current['content'],
            $this->inputCharacterBudget - mb_strlen($system) - self::MESSAGE_OVERHEAD,
            self::MESSAGE_OMISSION_MARKER,
            0.7,
        );
        $selected = [$current];
        $remaining = $this->inputCharacterBudget
            - mb_strlen($system)
            - $this->messageCost($current);

        foreach (array_reverse($messages) as $message) {
            $cost = $this->messageCost($message);
            if ($cost > $remaining) {
                break;
            }

            array_unshift($selected, $message);
            $remaining -= $cost;
        }

        return new AiProviderPrompt($system, $selected);
    }

    private function compactSystem(string $system): string
    {
        if (mb_strlen($system) <= $this->systemCharacterBudget) {
            return $system;
        }

        return $this->compactText($system, $this->systemCharacterBudget, self::OMISSION_MARKER, 0.65);
    }

    private function messageCost(array $message): int
    {
        return mb_strlen((string) $message['content']) + self::MESSAGE_OVERHEAD;
    }

    private function compactText(string $text, int $limit, string $marker, float $headRatio): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $available = max(0, $limit - mb_strlen($marker));
        $headLength = (int) floor($available * $headRatio);
        $tailLength = $available - $headLength;

        return mb_substr($text, 0, $headLength) . $marker . mb_substr($text, -$tailLength);
    }
}
