<?php

declare(strict_types=1);

class DealStageMapper
{
    public function mapFromRenovatioStatus(string $status, ?string $statusId = null): string
    {
        if ($status === 'refused') {
            return 'C1:4';
        }
        if ($status === 'completed') {
            return 'C1:WON';
        }
        if ($status === 'upcoming' && $statusId === '3') {
            return 'C1:UC_Q3I0Z1';
        }

        return '';
    }
}
