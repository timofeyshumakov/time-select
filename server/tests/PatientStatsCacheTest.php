<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PatientStatsCacheTest extends TestCase
{
    private string $dir;
    private PatientStatsCache $cache;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/azbyka_cache_' . uniqid('', true) . '/';
        $this->cache = new PatientStatsCache($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testSetAndGet(): void
    {
        $this->cache->set(123, ['ok' => true]);
        $data = $this->cache->get(123);
        $this->assertNotNull($data);
        $this->assertTrue($data['ok']);
        $this->assertArrayHasKey('cached_at', $data);
    }

    public function testClear(): void
    {
        $this->cache->set(123, ['ok' => true]);
        $this->cache->clear(123);
        $this->assertNull($this->cache->get(123));
    }

    public function testMiss(): void
    {
        $this->assertNull($this->cache->get(999));
    }
}
