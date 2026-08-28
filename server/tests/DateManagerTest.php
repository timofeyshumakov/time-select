<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DateManagerTest extends TestCase
{
    private DateManager $dm;

    protected function setUp(): void
    {
        $this->dm = new DateManager('Europe/Moscow');
    }

    public function testBitrixIsoToRenovatio(): void
    {
        $result = $this->dm->bitrixToRenovatio('2025-11-06T12:12:00+03:00');
        $this->assertSame('06.11.2025 12:12', $result);
    }

    public function testBitrixIsoOtherTimezoneNormalizedToMoscow(): void
    {
        // GMT+5 14:12 → Moscow (+3) 12:12
        $result = $this->dm->bitrixToRenovatio('2025-11-06T14:12:00+05:00');
        $this->assertSame('06.11.2025 12:12', $result);
    }

    public function testRenovatioToBitrix(): void
    {
        $result = $this->dm->renovatioToBitrix('06.11.2025 12:12');
        $this->assertSame('2025-11-06T12:12:00+03:00', $result);
    }

    public function testFormatDateForBitrixFromDotFormat(): void
    {
        $this->assertSame('2025-11-06', $this->dm->formatDateForBitrix('06.11.2025'));
    }

    public function testIsValidRenovatioDate(): void
    {
        $this->assertTrue($this->dm->isValidRenovatioDate('06.11.2025 12:12'));
        $this->assertFalse($this->dm->isValidRenovatioDate('2025-11-06'));
    }

    public function testInvalidBitrixThrows(): void
    {
        $this->expectException(Exception::class);
        $this->dm->bitrixToRenovatio('not-a-date');
    }
}
