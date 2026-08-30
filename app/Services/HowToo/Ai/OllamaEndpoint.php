<?php

namespace Pterodactyl\Services\HowToo\Ai;

final class OllamaEndpoint
{
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('A valid Ollama base URL is required.');
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('The Ollama base URL must use HTTP or HTTPS without credentials, query, or fragment.');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '' && $path !== '/api') {
            throw new \InvalidArgumentException('The Ollama base URL path must be empty or /api.');
        }

        $host = str_contains((string) $parts['host'], ':') ? '[' . $parts['host'] . ']' : $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower((string) $parts['scheme']) . '://' . $host . $port;
    }

    public static function url(?string $baseUrl, string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        $endpoint = preg_replace('#^api/#', '', $endpoint) ?? '';

        return self::normalize($baseUrl) . '/api/' . $endpoint;
    }
}
