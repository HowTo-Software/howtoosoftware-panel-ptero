<?php

namespace Pterodactyl\Services\HowToo;

use Illuminate\Support\Str;

final class AiConversationShortcut
{
    public function response(string $message): ?string
    {
        $normalized = mb_strtolower(Str::ascii(trim($message)));
        $normalized = trim(preg_replace('/[^a-z0-9\s]+/', '', $normalized) ?? '');
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return match ($normalized) {
            'ola', 'oi', 'opa', 'e ai' => 'Olá! Como posso ajudar?',
            'bom dia' => 'Bom dia! Como posso ajudar?',
            'boa tarde' => 'Boa tarde! Como posso ajudar?',
            'boa noite' => 'Boa noite! Como posso ajudar?',
            'hello', 'hi', 'hey' => 'Hello! How can I help?',
            'hola' => '¡Hola! ¿Cómo puedo ayudarte?',
            default => null,
        };
    }
}
