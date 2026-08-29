<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\HowToo\ServerGameContext;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\HowToo\ZomboidWorkshopConfigService;

class ZomboidWorkshopConfigServiceTest extends TestCase
{
    private ZomboidWorkshopConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ZomboidWorkshopConfigService(
            \Mockery::mock(DaemonFileRepository::class),
            new ServerGameContext(),
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItParsesOrderedSemicolonListsAndRemovesDuplicates(): void
    {
        $result = $this->service->parseContent(implode("\r\n", [
            'PublicName=Example',
            'WorkshopItems=123;456;123;;789',
            'Mods=Alpha;Beta;Alpha',
        ]));

        $this->assertSame(['123', '456', '789'], $result['workshop_items']);
        $this->assertSame(['Alpha', 'Beta'], $result['mods']);
    }

    public function testItUpdatesOnlyWorkshopKeysAndPreservesLineEndings(): void
    {
        $content = "PublicName=Example\r\nWorkshopItems=1;2\r\nMods=Old\r\nWorkshopItems=duplicate\r\n";
        $updated = $this->service->updateContent($content, ['9', '8', '9'], ['New', 'Other', 'New']);

        $this->assertSame(
            "PublicName=Example\r\nWorkshopItems=9;8\r\nMods=New;Other\r\n",
            $updated,
        );
    }

    public function testItAppendsMissingKeysWithoutChangingOtherSettings(): void
    {
        $updated = $this->service->updateContent("PublicName=Example\nPauseEmpty=true", ['42'], ['ExampleMod']);

        $this->assertSame(
            "PublicName=Example\nPauseEmpty=true\nWorkshopItems=42\nMods=ExampleMod",
            $updated,
        );
    }
}
