<?php

namespace Tests\Feature;

use Tests\TestCase;

class GlideCacheConfigTest extends TestCase
{
    public function test_glide_disk_points_at_r2_img_root(): void
    {
        $disk = config('filesystems.disks.glide');

        $this->assertSame('s3', $disk['driver']);
        $this->assertSame(env('R2_BUCKET'), $disk['bucket']);
        $this->assertStringEndsWith('img', $disk['root']);
        $this->assertStringEndsWith('/img', $disk['url']);
    }

    public function test_glide_cache_is_env_driven_and_off_by_default(): void
    {
        // In tests is GLIDE_CACHE niet gezet: dynamische route blijft actief.
        $this->assertFalse(config('statamic.assets.image_manipulation.cache'));
    }
}
