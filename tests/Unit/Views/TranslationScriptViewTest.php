<?php

namespace Pterodactyl\Tests\Unit\Views;

use Pterodactyl\Tests\TestCase;

class TranslationScriptViewTest extends TestCase
{
    public function testTranslationScriptRendersCountPlaceholderWithoutBladeInterpretingIt(): void
    {
        app()->setLocale('en');

        $html = view('layouts.scripts')->render();

        self::assertStringContainsString('var allocationCountKey = \'and \' + "{{count}}" + \' other allocations\';', $html);
        self::assertStringContainsString(
            'window.HowTooTranslations[allocationCountKey] = "and {{count}} other allocations";',
            $html
        );
        self::assertStringContainsString("var placeholder = '{' + '{' + name + '}' + '}';", $html);
    }

    public function testBladeViewsDoNotContainUnescapedBarePlaceholderEchoes(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        $pattern = '/(?<!@)\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}/';

        foreach ($views as $view) {
            if (!$view->isFile() || !str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($view->getPathname());
            self::assertDoesNotMatchRegularExpression(
                $pattern,
                $source,
                sprintf('Unescaped bare placeholder found in Blade view %s.', $view->getPathname())
            );
        }
    }
}
