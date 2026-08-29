<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\Ai\AiPromptBudgeter;
use Pterodactyl\Services\HowToo\Ai\AiProviderPrompt;

class AiPromptBudgeterTest extends TestCase
{
    public function testItPreservesTheCurrentQuestionAndDropsOldContextToFitTheBudget(): void
    {
        $system = 'SAFETY-' . str_repeat('a', 9000) . '-SERVER-CONTEXT';
        $messages = [];
        for ($index = 0; $index < 8; ++$index) {
            $messages[] = [
                'role' => $index % 2 === 0 ? 'user' : 'assistant',
                'content' => "history-$index-" . str_repeat('h', 1800),
            ];
        }
        $messages[] = ['role' => 'user', 'content' => 'CURRENT-' . str_repeat('q', 2500)];

        $result = (new AiPromptBudgeter(10000, 5500))->compact(new AiProviderPrompt($system, $messages));
        $length = mb_strlen($result->system)
            + collect($result->messages)->sum(fn (array $message): int => mb_strlen($message['content']) + 32);

        $this->assertLessThanOrEqual(10000, $length);
        $this->assertStringStartsWith('SAFETY-', $result->system);
        $this->assertStringEndsWith('-SERVER-CONTEXT', $result->system);
        $this->assertStringContainsString('contextual details omitted', $result->system);
        $resultMessages = $result->messages;
        $this->assertSame(end($messages), end($resultMessages));
        $this->assertLessThan(count($messages), count($result->messages));
    }

    public function testItCompactsAnOversizedCurrentQuestionWithoutLosingItsBoundaries(): void
    {
        $question = 'IMPORTANT START ' . str_repeat('specific detail ', 2000) . ' IMPORTANT END';
        $prompt = new AiProviderPrompt('System context', [['role' => 'user', 'content' => $question]]);

        $compacted = (new AiPromptBudgeter(8000, 4000))->compact($prompt);
        $content = $compacted->messages[0]['content'];
        $length = mb_strlen($compacted->system) + mb_strlen($content) + 32;

        $this->assertStringStartsWith('IMPORTANT START', $content);
        $this->assertStringEndsWith('IMPORTANT END', $content);
        $this->assertStringContainsString('middle of this message omitted', $content);
        $this->assertLessThanOrEqual(8000, $length);
    }
}
