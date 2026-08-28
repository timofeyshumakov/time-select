<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FreeSlotCalculatorTest extends TestCase
{
    private FreeSlotCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new FreeSlotCalculator();
    }

    public function testBuildFreeSlotsSkippingBusyAppointment(): void
    {
        $schedule = [
            [
                'id' => 1,
                'user_id' => '10',
                'clinic_id' => '20',
                'type' => 1,
                'time_start' => '01.01.2025 10:00',
                'time_end' => '01.01.2025 10:30',
            ],
        ];
        $appointments = [
            [
                'doctor_id' => '10',
                'clinic_id' => '20',
                'status' => 'upcoming',
                'time_start' => '01.01.2025 10:00',
                'time_end' => '01.01.2025 10:10',
            ],
        ];

        $result = $this->calc->build('10', '20', $schedule, $appointments, 10);
        $slots = $result['10'] ?? [];

        $this->assertCount(2, $slots);
        $this->assertSame('10:10', $slots[0]['time_start_short']);
        $this->assertSame('10:20', $slots[1]['time_start_short']);
    }

    public function testCancelledAppointmentDoesNotBlock(): void
    {
        $schedule = [
            [
                'id' => 1,
                'user_id' => '10',
                'clinic_id' => '20',
                'type' => 1,
                'time_start' => '01.01.2025 10:00',
                'time_end' => '01.01.2025 10:20',
            ],
        ];
        $appointments = [
            [
                'doctor_id' => '10',
                'clinic_id' => '20',
                'status' => 'cancelled',
                'time_start' => '01.01.2025 10:00',
                'time_end' => '01.01.2025 10:10',
            ],
        ];

        $result = $this->calc->build('10', '20', $schedule, $appointments, 10);
        $this->assertCount(2, $result['10']);
    }

    public function testIsAppointmentCancelled(): void
    {
        $this->assertTrue($this->calc->isAppointmentCancelled(['status' => 'refused']));
        $this->assertTrue($this->calc->isAppointmentCancelled(['status_id' => 5]));
        $this->assertFalse($this->calc->isAppointmentCancelled(['status' => 'upcoming']));
    }

    public function testIsListArray(): void
    {
        $this->assertTrue($this->calc->isListArray([1, 2, 3]));
        $this->assertTrue($this->calc->isListArray([]));
        $this->assertFalse($this->calc->isListArray(['a' => 1]));
    }

    public function testParseRenovatioDateTime(): void
    {
        $date = $this->calc->parseRenovatioDateTime('06.11.2025 12:12');
        $this->assertInstanceOf(DateTime::class, $date);
        $this->assertSame('2025-11-06 12:12', $date->format('Y-m-d H:i'));
    }
}
