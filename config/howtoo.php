<?php

return [
    'providers' => [
        'gemini' => [
            'enabled' => env('FEATURE_AI_SUPPORT', false),
            'secret' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL') ?: 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com',
        ],
        'groq' => [
            'enabled' => env('FEATURE_AI_HELPER', false),
            'secret' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
            'base_url' => 'https://api.groq.com/openai/v1',
        ],
        'steam' => [
            'enabled' => env('FEATURE_STEAM_WORKSHOP', false),
            'secret' => env('STEAM_WEB_API_KEY'),
            'model' => null,
            'app_id' => (int) env('STEAM_PZ_APP_ID', 108600),
            'base_url' => 'https://api.steampowered.com',
        ],
        'curseforge' => [
            'enabled' => env('FEATURE_CURSEFORGE', false),
            'secret' => env('CURSEFORGE_API_KEY'),
            'model' => null,
            'base_url' => 'https://api.curseforge.com',
        ],
    ],
];
