<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Server;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\EggVariable;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Services\HowToo\ServerGameContext;

class ServerGameContextTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItDetectsTheOfficialVanillaMinecraftEggAndItsVersion(): void
    {
        $context = (new ServerGameContext())->for($this->server('Vanilla Minecraft', [
            ['env_variable' => 'SERVER_VERSION', 'server_value' => 'latest', 'default_value' => 'latest'],
            ['env_variable' => 'VANILLA_VERSION', 'server_value' => '1.20.1', 'default_value' => 'latest'],
        ]));

        $this->assertTrue($context['minecraft']);
        $this->assertTrue($context['minecraft_java']);
        $this->assertSame('java', $context['minecraft_edition']);
        $this->assertSame('1.20.1', $context['minecraft_version']);
        $this->assertSame('Vanilla', $context['mod_loader']);
        $this->assertSame(0, $context['mod_loader_type']);
    }

    public function testItIdentifiesBedrockWithoutClaimingItIsAJavaRuntime(): void
    {
        $context = (new ServerGameContext())->for($this->server('Bedrock Dedicated Server', [
            ['env_variable' => 'BEDROCK_VERSION', 'server_value' => '1.21.80.3', 'default_value' => 'latest'],
        ]));

        $this->assertTrue($context['minecraft']);
        $this->assertFalse($context['minecraft_java']);
        $this->assertSame('bedrock', $context['minecraft_edition']);
        $this->assertSame('1.21.80.3', $context['minecraft_version']);
        $this->assertNull($context['mod_loader_type']);
    }

    public function testItDetectsBedrockWhenTheEggOnlyNamesItInAServerVariable(): void
    {
        $context = (new ServerGameContext())->for($this->server('Minecraft Server', [
            ['env_variable' => 'BEDROCK_VERSION', 'server_value' => '1.21.80.3', 'default_value' => 'latest'],
            ['env_variable' => 'SERVER_SOFTWARE', 'server_value' => 'Bedrock', 'default_value' => 'Bedrock'],
        ]));

        $this->assertFalse($context['minecraft_java']);
        $this->assertSame('bedrock', $context['minecraft_edition']);
    }

    public function testItRecognizesLoaderTypesFromEggVariables(): void
    {
        $context = (new ServerGameContext())->for($this->server('Minecraft Java Server', [
            ['env_variable' => 'MC_VERSION', 'server_value' => '1.21.1', 'default_value' => 'latest'],
            ['env_variable' => 'MOD_LOADER_TYPE', 'server_value' => 'NeoForge', 'default_value' => ''],
        ]));

        $this->assertSame('1.21.1', $context['minecraft_version']);
        $this->assertSame('NeoForge', $context['mod_loader']);
        $this->assertSame(6, $context['mod_loader_type']);
    }

    public function testItDoesNotMistakePluginServersForVanillaModLoaders(): void
    {
        $context = (new ServerGameContext())->for($this->server('Paper Minecraft', [
            ['env_variable' => 'MINECRAFT_VERSION', 'server_value' => '1.20.4', 'default_value' => 'latest'],
        ]));

        $this->assertTrue($context['minecraft_java']);
        $this->assertNull($context['mod_loader']);
        $this->assertNull($context['mod_loader_type']);
    }

    private function server(string $eggName, array $variables): Server
    {
        $server = \Mockery::mock(Server::class)->makePartial();
        $server->setRelation('egg', (new Egg())->forceFill(['name' => $eggName]));
        $server->setRelation('nest', (new Nest())->forceFill(['name' => 'Minecraft']));
        $server->setRelation('variables', new Collection(array_map(
            fn (array $variable): EggVariable => (new EggVariable())->forceFill($variable),
            $variables,
        )));

        return $server;
    }
}
