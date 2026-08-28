<?php

declare(strict_types=1);

class FreeSlotCalculator
{
    public function build(
        string $doctorId,
        string $clinicId,
        array $schedulePeriods,
        array $appointments,
        int $stepMinutes = 10
    ): array {
        $workingPeriods = array_values(array_filter($schedulePeriods, function ($period) use ($doctorId, $clinicId) {
            if (!is_array($period)) {
                return false;
            }

            return (string)($period['user_id'] ?? '') === $doctorId
                && (string)($period['clinic_id'] ?? '') === $clinicId
                && (int)($period['type'] ?? 0) === 1;
        }));

        if (empty($workingPeriods)) {
            return [];
        }

        $busyRanges = [];

        foreach ($schedulePeriods as $period) {
            if (!is_array($period)) {
                continue;
            }
            if ((string)($period['user_id'] ?? '') !== $doctorId || (string)($period['clinic_id'] ?? '') !== $clinicId) {
                continue;
            }
            if ((int)($period['type'] ?? 0) === 1) {
                continue;
            }

            $start = $this->parseRenovatioDateTime($period['time_start'] ?? null);
            $end = $this->parseRenovatioDateTime($period['time_end'] ?? null);
            if ($start && $end && $start < $end) {
                $busyRanges[] = ['start' => $start, 'end' => $end];
            }
        }

        foreach ($appointments as $appointment) {
            if (!is_array($appointment)) {
                continue;
            }
            if ((string)($appointment['doctor_id'] ?? '') !== $doctorId || (string)($appointment['clinic_id'] ?? '') !== $clinicId) {
                continue;
            }
            if ($this->isAppointmentCancelled($appointment)) {
                continue;
            }

            $start = $this->parseRenovatioDateTime($appointment['time_start'] ?? null);
            $end = $this->parseRenovatioDateTime($appointment['time_end'] ?? null);
            if ($start && $end && $start < $end) {
                $busyRanges[] = ['start' => $start, 'end' => $end];
            }
        }

        $result = [$doctorId => []];
        $seenSlots = [];

        foreach ($workingPeriods as $period) {
            $workStart = $this->parseRenovatioDateTime($period['time_start'] ?? null);
            $workEnd = $this->parseRenovatioDateTime($period['time_end'] ?? null);
            if (!$workStart || !$workEnd || $workStart >= $workEnd) {
                continue;
            }

            $current = clone $workStart;
            while ($current < $workEnd) {
                $slotEnd = (clone $current)->modify("+{$stepMinutes} minutes");
                if ($slotEnd > $workEnd) {
                    break;
                }

                if (!$this->rangeOverlapsBusyRanges($current, $slotEnd, $busyRanges)) {
                    $slotKey = $current->format('Y-m-d H:i:s') . '_' . $slotEnd->format('Y-m-d H:i:s');
                    if (!isset($seenSlots[$slotKey])) {
                        $result[$doctorId][] = $this->formatFreeSlot($period, $current, $slotEnd);
                        $seenSlots[$slotKey] = true;
                    }
                }

                $current = $slotEnd;
            }
        }

        usort($result[$doctorId], function ($a, $b) {
            return strcmp($a['time_start'], $b['time_start']);
        });

        return $result;
    }

    public function isListArray($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return empty($value) || array_keys($value) === range(0, count($value) - 1);
    }

    public function parseRenovatioDateTime(?string $value): ?DateTime
    {
        if (!$value) {
            return null;
        }

        foreach (['d.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date;
            }
        }

        return null;
    }

    public function isAppointmentCancelled(array $appointment): bool
    {
        $status = mb_strtolower((string)($appointment['status'] ?? ''), 'UTF-8');
        $cancelledStatuses = ['refused', 'cancelled', 'canceled'];

        return in_array($status, $cancelledStatuses, true)
            || (int)($appointment['status_id'] ?? 0) === 5;
    }

    private function rangeOverlapsBusyRanges(DateTime $start, DateTime $end, array $busyRanges): bool
    {
        foreach ($busyRanges as $range) {
            if ($start < $range['end'] && $end > $range['start']) {
                return true;
            }
        }

        return false;
    }

    private function formatFreeSlot(array $period, DateTime $start, DateTime $end): array
    {
        $date = $start->format('d.m.Y');

        return [
            'schedule_id' => $period['id'] ?? $period['schedule_id'] ?? null,
            'user_id' => $period['user_id'] ?? null,
            'clinic_id' => $period['clinic_id'] ?? null,
            'date' => $date,
            'time_start' => $start->format('Y-m-d H:i:s'),
            'time_end' => $end->format('Y-m-d H:i:s'),
            'time' => $start->format('H:i') . ' - ' . $end->format('H:i'),
            'time_start_short' => $start->format('H:i'),
            'time_end_short' => $end->format('H:i'),
            'category_id' => $period['category_id'] ?? null,
            'room' => $period['room'] ?? null,
            'is_busy' => false,
            'is_past' => false,
            '_date' => $start->format('Y-m-d'),
            'beautyDate' => $date,
            'extra' => [10],
            '_extra' => 10,
        ];
    }
}
