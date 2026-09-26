<?php

namespace Pterodactyl\Tests\Unit\Lang;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TranslationCoverageTest extends TestCase
{
    public function testPortugueseAndEnglishPhpCatalogsHaveMatchingKeys(): void
    {
        $root = dirname(__DIR__, 3) . '/resources/lang';
        $englishFiles = $this->phpFiles($root . '/en');
        $portugueseFiles = $this->phpFiles($root . '/pt');

        self::assertSame(array_keys($englishFiles), array_keys($portugueseFiles));

        foreach ($englishFiles as $relativePath => $englishFile) {
            $english = require $englishFile;
            $portuguese = require $portugueseFiles[$relativePath];

            self::assertSame(
                $this->flattenKeys($english),
                $this->flattenKeys($portuguese),
                "The PT-BR catalog at {$relativePath} must have the same keys as English."
            );
        }
    }

    public function testBladeLiteralTranslationsExistInThePortugueseJsonCatalog(): void
    {
        $root = dirname(__DIR__, 3);
        $catalog = json_decode(file_get_contents($root . '/resources/lang/pt.json'), true, flags: JSON_THROW_ON_ERROR);
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views'));
        $pattern = '/__\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")/s';

        foreach ($views as $view) {
            if (!$view->isFile() || !str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($view->getPathname());
            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $key = stripcslashes(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''));
                self::assertArrayHasKey(
                    $key,
                    $catalog,
                    sprintf('%s uses a literal without a PT-BR translation: %s', $view->getPathname(), $key)
                );
            }
        }
    }

    /** @return array<string, string> */
    private function phpFiles(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $result = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $result[substr($path, strlen($directory) + 1)] = $path;
        }

        ksort($result);

        return $result;
    }

    /** @return array<int, string> */
    private function flattenKeys(array $catalog, string $prefix = ''): array
    {
        $keys = [];

        foreach ($catalog as $key => $value) {
            $qualified = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $keys[] = $qualified;

            if (is_array($value)) {
                array_pop($keys);
                $keys = [...$keys, ...$this->flattenKeys($value, $qualified)];
            }
        }

        sort($keys);

        return $keys;
    }
}
