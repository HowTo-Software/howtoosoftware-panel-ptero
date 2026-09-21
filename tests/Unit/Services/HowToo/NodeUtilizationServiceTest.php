<?php

namespace Pterodactyl\Tests\Unit\Services\HowToo;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\HowToo\NodeUtilizationService;

class NodeUtilizationServiceTest extends TestCase
{
    private \ReflectionClass $reflection;
    private NodeUtilizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new \ReflectionClass(NodeUtilizationService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testSummariseReturnsUnavailablePayloadWhenServersRequestFails(): void
    {
        $result = $this->invoke('summarise', [
            $this->node(memory: 4096, disk: 10240),
            null,
            ['system' => ['cpu_threads' => 8, 'memory_bytes' => 16 * 1024 * 1024 * 1024]],
        ]);

        $this->assertFalse($result['reachable']);
        $this->assertNull($result['online']);
        $this->assertSame('unknown', $result['cpu']['status']);
        $this->assertSame('unknown', $result['memory']['status']);
        $this->assertSame('unknown', $result['disk']['status']);
    }

    public function testSummariseCountsRunningServersAndUsesV2SystemTotals(): void
    {
        $result = $this->invoke('summarise', [
            $this->node(memory: 4096, disk: 10240),
            [
                ['state' => 'running', 'utilization' => ['cpu_absolute' => 35.2, 'memory_bytes' => 512, 'disk_bytes' => 1024]],
                ['state' => 'running', 'utilization' => ['cpu_absolute' => 4.8, 'memory_bytes' => 1024, 'disk_bytes' => 2048]],
                ['state' => 'offline', 'utilization' => ['cpu_absolute' => 10.0, 'memory_bytes' => 4096, 'disk_bytes' => 8192]],
            ],
            ['system' => ['cpu_threads' => 8, 'memory_bytes' => 16 * 1024 * 1024 * 1024]],
        ]);

        $this->assertTrue($result['reachable']);
        $this->assertSame(2, $result['online']);
        $this->assertSame(50.0, $result['cpu']['used']);
        $this->assertSame(800, $result['cpu']['total']);
        $this->assertSame('daemon', $result['cpu']['source']);
        $this->assertSame(16 * 1024 * 1024 * 1024, $result['memory']['total']);
        $this->assertSame('daemon', $result['memory']['source']);
    }

    public function testSummariseSupportsV1SystemShapeAndConfiguredFallbacks(): void
    {
        $result = $this->invoke('summarise', [
            $this->node(memory: 4096, disk: 10240),
            [['state' => 'running', 'utilization' => ['cpu_absolute' => 100.0, 'memory_bytes' => 2048, 'disk_bytes' => 4096]]],
            ['cpu_count' => 4],
        ]);

        $this->assertTrue($result['reachable']);
        $this->assertSame(1, $result['online']);
        $this->assertSame(400, $result['cpu']['total']);
        $this->assertSame('daemon', $result['cpu']['source']);
        $this->assertSame(4096 * 1024 * 1024, $result['memory']['total']);
        $this->assertSame('configured', $result['memory']['source']);
    }

    public function testSummariseKeepsConfiguredSourceWhenCapacityIsZero(): void
    {
        $result = $this->invoke('summarise', [
            $this->node(memory: 0, disk: 0),
            [['state' => 'running', 'utilization' => ['cpu_absolute' => 5.0, 'memory_bytes' => 1024, 'disk_bytes' => 2048]]],
            null,
        ]);

        $this->assertSame('unlimited', $result['memory']['status']);
        $this->assertSame('configured', $result['memory']['source']);
        $this->assertNull($result['memory']['total']);
        $this->assertSame('unlimited', $result['disk']['status']);
        $this->assertSame('configured', $result['disk']['source']);
        $this->assertNull($result['disk']['total']);
    }

    private function node(int $memory, int $disk): Node
    {
        $node = new Node(['memory' => $memory, 'disk' => $disk]);
        $node->id = 1;

        return $node;
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $reflection = $this->reflection->getMethod($method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->service, $arguments);
    }
}
