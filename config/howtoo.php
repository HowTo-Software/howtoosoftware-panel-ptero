<?php

return [
    'assistant' => [
        'total_timeout_seconds' => (int) env('AI_TOTAL_TIMEOUT_SECONDS', 90),
        'input_character_budget' => (int) env('AI_INPUT_CHARACTER_BUDGET', 20000),
        'system_character_budget' => (int) env('AI_SYSTEM_CHARACTER_BUDGET', 12000),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1000),
    ],
    'providers' => [
        'gemini' => [
            'enabled' => env('FEATURE_AI_SUPPORT', false),
            'priority' => 10,
            'timeout_seconds' => (int) env('GEMINI_TIMEOUT_SECONDS', 40),
            'secret' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL') ?: 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com',
        ],
        'groq' => [
            'enabled' => env('FEATURE_AI_HELPER', false),
            'priority' => 20,
            'timeout_seconds' => (int) env('GROQ_TIMEOUT_SECONDS', 35),
            'secret' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
            'base_url' => 'https://api.groq.com/openai/v1',
        ],
        'steam' => [
            'enabled' => env('FEATURE_STEAM_WORKSHOP', false),
            'priority' => 100,
            'timeout_seconds' => 20,
            'secret' => env('STEAM_WEB_API_KEY'),
            'model' => null,
            'app_id' => (int) env('STEAM_PZ_APP_ID', 108600),
            'base_url' => 'https://api.steampowered.com',
        ],
        'curseforge' => [
            'enabled' => env('FEATURE_CURSEFORGE', false),
            'priority' => 100,
            'timeout_seconds' => 25,
            'secret' => env('CURSEFORGE_API_KEY'),
            'model' => null,
            'base_url' => 'https://api.curseforge.com',
        ],
    ],
];
