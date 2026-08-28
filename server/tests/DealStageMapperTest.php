<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DealStageMapperTest extends TestCase
{
    private DealStageMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DealStageMapper();
    }

    public function testRefused(): void
    {
        $this->assertSame('C1:4', $this->mapper->mapFromRenovatioStatus('refused'));
    }

    public function testCompleted(): void
    {
        $this->assertSame('C1:WON', $this->mapper->mapFromRenovatioStatus('completed'));
    }

    public function testClientArrived(): void
    {
        $this->assertSame('C1:UC_Q3I0Z1', $this->mapper->mapFromRenovatioStatus('upcoming', '3'));
    }

    public function testUpcomingWithoutStatusId(): void
    {
        $this->assertSame('', $this->mapper->mapFromRenovatioStatus('upcoming', '1'));
        $this->assertSame('', $this->mapper->mapFromRenovatioStatus('upcoming'));
    }

    public function testUnknown(): void
    {
        $this->assertSame('', $this->mapper->mapFromRenovatioStatus('unknown'));
    }
}
