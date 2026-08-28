<?php

declare(strict_types=1);

class PatientStatsCache
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? (__DIR__ . '/../cache/patient_stats/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(int $patientId): ?array
    {
        $file = $this->getCacheFile($patientId);
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (isset($data['cached_at']) && (time() - $data['cached_at']) < 3600) {
            return $data;
        }

        return null;
    }

    public function set(int $patientId, array $data): void
    {
        $file = $this->getCacheFile($patientId);
        $data['cached_at'] = time();
        $data['cached_date'] = date('Y-m-d H:i:s');
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function clear(int $patientId): void
    {
        $file = $this->getCacheFile($patientId);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function getCacheFile(int $patientId): string
    {
        return $this->cacheDir . 'patient_' . $patientId . '_stats.json';
    }
}
