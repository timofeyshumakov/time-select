<?php

declare(strict_types=1);

class DateManager
{
    private const BITRIX_FORMAT = 'Y-m-d H:i';
    private const ISO_FORMAT = 'Y-m-d\TH:i:sP';
    private const RENOVATIO_FORMAT = 'd.m.Y H:i';
    private const DEFAULT_TIMEZONE = 'Europe/Moscow';

    private DateTimeZone $timezone;

    public function __construct(?string $timezone = null)
    {
        $this->timezone = new DateTimeZone($timezone ?? self::DEFAULT_TIMEZONE);
    }

    public function bitrixToRenovatio(string $bitrixDate): string
    {
        try {
            $date = DateTime::createFromFormat(self::ISO_FORMAT, $bitrixDate);
            if ($date) {
                $date->setTimezone($this->timezone);
                return $date->format(self::RENOVATIO_FORMAT);
            }

            $date = DateTime::createFromFormat(self::BITRIX_FORMAT, $bitrixDate, $this->timezone);
            if (!$date) {
                throw new Exception("Неверный формат даты Bitrix24: {$bitrixDate}");
            }

            return $date->format(self::RENOVATIO_FORMAT);
        } catch (Exception $e) {
            throw new Exception("Ошибка преобразования даты из Bitrix24: " . $e->getMessage());
        }
    }

    public function renovatioToBitrix(string $renovatioDate): string
    {
        try {
            $date = DateTime::createFromFormat(self::RENOVATIO_FORMAT, $renovatioDate, $this->timezone);
            if (!$date) {
                throw new Exception("Неверный формат даты Renovatio: {$renovatioDate}");
            }

            return $date->format(self::ISO_FORMAT);
        } catch (Exception $e) {
            throw new Exception("Ошибка преобразования даты в Bitrix24: " . $e->getMessage());
        }
    }

    public function convertArrayBitrixToRenovatio(array $dates): array
    {
        $result = [];
        foreach ($dates as $key => $date) {
            try {
                $result[$key] = $this->bitrixToRenovatio($date);
            } catch (Exception $e) {
                $result[$key] = null;
                error_log("Date conversion error for key {$key}: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function convertArrayRenovatioToBitrix(array $dates): array
    {
        $result = [];
        foreach ($dates as $key => $date) {
            try {
                $result[$key] = $this->renovatioToBitrix($date);
            } catch (Exception $e) {
                $result[$key] = null;
                error_log("Date conversion error for key {$key}: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function isValidBitrixDate(string $date): bool
    {
        $d = DateTime::createFromFormat(self::BITRIX_FORMAT, $date);
        return $d && $d->format(self::BITRIX_FORMAT) === $date;
    }

    public function isValidRenovatioDate(string $date): bool
    {
        $d = DateTime::createFromFormat(self::RENOVATIO_FORMAT, $date);
        return $d && $d->format(self::RENOVATIO_FORMAT) === $date;
    }

    public function getCurrentBitrixDate(): string
    {
        return (new DateTime('now', $this->timezone))->format(self::BITRIX_FORMAT);
    }

    public function getCurrentRenovatioDate(): string
    {
        return (new DateTime('now', $this->timezone))->format(self::RENOVATIO_FORMAT);
    }

    public function formatDateForBitrix(string $date): string
    {
        try {
            $dateObj = DateTime::createFromFormat(self::ISO_FORMAT, $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }

            $dateObj = DateTime::createFromFormat('d.m.Y', $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }

            $dateObj = DateTime::createFromFormat(self::BITRIX_FORMAT, $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
        } catch (Exception $e) {
            error_log("Date format error for '{$date}': " . $e->getMessage());
        }

        return $date;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function getTimezone(): string
    {
        return $this->timezone->getName();
    }
}
