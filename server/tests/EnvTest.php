<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('fallback', Env::get('___missing_env_key_xyz___', 'fallback'));
    }

    public function testRequireThrowsWhenMissing(): void
    {
        $this->expectException(RuntimeException::class);
        Env::require('___missing_env_key_xyz___');
    }
}
