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

    public function testTextSearchParametersExposeIndependentPages(): void
    {
        $pageOne = $this->invoke('queryParameters', ['secret', 'Authentic Z', 1, 30]);
        $pageTwo = $this->invoke('queryParameters', ['secret', 'Authentic Z', 2, 30]);

        $this->assertSame(12, $pageOne['query_type']);
        $this->assertSame('Authentic Z', $pageOne['search_text']);
        $this->assertSame(1, $pageOne['page']);
        $this->assertSame(2, $pageTwo['page']);
        $this->assertSame(30, $pageTwo['numperpage']);
        $this->assertSame(108600, $pageTwo['appid']);
        $this->assertSame(108600, $pageTwo['creator_appid']);
    }

    public function testPaginationIncludesTotalPagesAndHasNext(): void
    {
        $pageOne = $this->invoke('result', [[['workshop_id' => '1']], 101, 1, 30, false]);
        $pageFour = $this->invoke('result', [[['workshop_id' => '101']], 101, 4, 30, false]);

        $this->assertSame(101, $pageOne['pagination']['total']);
        $this->assertSame(4, $pageOne['pagination']['total_pages']);
        $this->assertTrue($pageOne['pagination']['has_next']);
        $this->assertFalse($pageFour['pagination']['has_next']);
        $this->assertNotSame($pageOne['items'], $pageFour['items']);
    }

    public function testPaginationDoesNotStopAtTheFormerPageLimit(): void
    {
        $page = $this->invoke('result', [[['workshop_id' => '50000']], 50100, 1000, 50, false]);
        $parameters = $this->invoke('queryParameters', ['secret', 'late catalog item', 1001, 50]);

        $this->assertTrue($page['pagination']['has_next']);
        $this->assertSame(1001, $parameters['page']);
    }

    public function testNumericIdAndWorkshopUrlResolveDirectly(): void
    {
        $this->assertSame('2785484298', $this->invoke('directWorkshopId', ['2785484298']));
        $this->assertSame('2785484298', $this->invoke('directWorkshopId', [
            'https://steamcommunity.com/sharedfiles/filedetails/?id=2785484298&searchtext=test',
        ]));
        $this->assertNull($this->invoke('directWorkshopId', [
            'https://steamcommunity.com/sharedfiles/filedetails/?searchtext=test',
        ]));
    }

    public function testExactTitleRanksFirstWithoutControllingPagination(): void
    {
        $items = [
            ['workshop_id' => '1', 'name' => 'Popular Tintin Collection', 'description' => 'Tintin content'],
            ['workshop_id' => '2', 'name' => 'Other Mod', 'description' => 'Includes support for Tintin'],
            ['workshop_id' => '3', 'name' => 'Tintin', 'description' => 'Exact result'],
            ['workshop_id' => '4', 'name' => 'Tintin Expanded', 'description' => 'Starts with the query'],
        ];

        $ranked = $this->invoke('rankResults', [$items, 'tintin']);

        $this->assertSame(['3', '4', '1', '2'], array_column($ranked, 'workshop_id'));
        $this->assertFalse($this->reflection->hasConstant('SEARCH_PAGE_DEPTH'));
        $this->assertFalse($this->reflection->hasConstant('SEARCH_RESULT_LIMIT'));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = $this->reflection->getMethod($method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->service, $arguments);
    }
}
