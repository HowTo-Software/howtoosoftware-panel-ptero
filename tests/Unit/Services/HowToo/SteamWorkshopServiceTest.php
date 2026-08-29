<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\SteamWorkshopService;

class SteamWorkshopServiceTest extends TestCase
{
    private \ReflectionClass $reflection;
    private SteamWorkshopService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflection = new \ReflectionClass(SteamWorkshopService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testItUsesSteamTextSearchWithADeeperResultPage(): void
    {
        $parameters = $this->invoke('queryParameters', ['secret', 'Authentic Z', 2]);

        $this->assertSame(12, $parameters['query_type']);
        $this->assertSame('Authentic Z', $parameters['search_text']);
        $this->assertSame(2, $parameters['page']);
        $this->assertSame(50, $parameters['numperpage']);
        $this->assertSame(108600, $parameters['appid']);
        $this->assertSame(108600, $parameters['creator_appid']);
        $this->assertSame(0, $parameters['cache_max_age_seconds']);
    }

    public function testExactModNameIsRankedBeforePopularPartialMatches(): void
    {
        $items = [
            ['workshop_id' => '1', 'name' => 'Popular Tintin Collection', 'description' => 'Tintin content'],
            ['workshop_id' => '2', 'name' => 'Other Mod', 'description' => 'Includes support for Tintin'],
            ['workshop_id' => '3', 'name' => 'Tintin', 'description' => 'Exact result'],
            ['workshop_id' => '4', 'name' => 'Tintin Expanded', 'description' => 'Starts with the query'],
        ];

        $ranked = $this->invoke('rankResults', [$items, 'tintin']);

        $this->assertSame(['3', '4', '1', '2'], array_column($ranked, 'workshop_id'));
    }

    public function testExactMatchingIgnoresCaseAccentsAndPunctuation(): void
    {
        $items = [
            ['workshop_id' => '10', 'name' => 'Ação: Total!', 'description' => ''],
        ];

        $this->assertTrue($this->invoke('containsExactTitle', [$items, 'acao total']));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = $this->reflection->getMethod($method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->service, $arguments);
    }
}
