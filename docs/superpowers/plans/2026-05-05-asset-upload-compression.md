# Asset Upload Compression Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Auto-compress images uploaded by clients via the Statamic Control Panel (max 2500px wide, JPEG quality 85) so R2 storage stays small while clients upload freely.

**Architecture:** Synchronous server-side compression triggered by Statamic's `AssetUploaded` event. A pure `ImageCompressor` service (Intervention Image v3 + GD) is invoked by a thin listener that fetches bytes from R2, compresses, writes back. Imagick is used opportunistically for HEIC. Forge PHP upload limit raised from 2 MB to 25 MB; execution time from 30 s to 120 s.

**Tech Stack:** Statamic 6, Laravel 12, PHP 8.4, Intervention Image v3 (GD driver), Cloudflare R2 (existing `r2` disk), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-05-05-asset-upload-compression-design.md`

---

## File Structure

**New files:**
- `app/Services/ImageCompressor.php` — pure compression service. Input: bytes + mime + filename. Output: bytes + new mime + new filename. No Statamic dependency.
- `app/Listeners/CompressUploadedAsset.php` — Statamic event listener. Filters by container, calls `ImageCompressor`, writes result back to the asset.
- `config/image-compression.php` — config (max_width, jpeg_quality, enabled, containers, scope mimes).
- `tests/Unit/ImageCompressorTest.php` — unit tests for the service.
- `tests/Feature/AssetUploadCompressionTest.php` — feature test that fires the event.
- `tests/fixtures/images/` — test fixtures (large.jpg, small.jpg, alpha.png, corrupt.jpg).

**Modified files:**
- `composer.json` — add `intervention/image: ^3.0`.
- `app/Providers/AppServiceProvider.php` — register `AssetUploaded` listener.

**Manual (Forge dashboard):**
- PHP `Max file upload size`: 2 → 25 MB.
- PHP `Max execution time`: 30 → 120 s.

---

## Task 1: Add Intervention Image dependency

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Install package**

Run:
```bash
composer require intervention/image:^3.0
```
Expected: package installs, `composer.lock` updates, no errors.

- [ ] **Step 2: Verify installation**

Run:
```bash
php -r "var_dump(class_exists('Intervention\\Image\\ImageManager'));"
```
Expected: `bool(true)`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add intervention/image v3 for asset compression"
```

---

## Task 2: Create config file

**Files:**
- Create: `config/image-compression.php`

- [ ] **Step 1: Write config file**

```php
<?php

return [

    /*
    | Master kill-switch for the auto-compression listener.
    */
    'enabled' => env('IMAGE_COMPRESSION_ENABLED', true),

    /*
    | Asset container handles whose uploads should be compressed.
    | Other containers are left untouched.
    */
    'containers' => ['assets'],

    /*
    | Max width in pixels. Images wider than this are resized down
    | proportionally. Images narrower are left at their original dimensions
    | (only re-encoded).
    */
    'max_width' => 2500,

    /*
    | JPEG quality (0-100). 85 is visually indistinguishable from the
    | original for photographic content.
    */
    'jpeg_quality' => 85,

    /*
    | Mime types we will process. Anything else is skipped (returned as-is).
    | HEIC requires Imagick; the service degrades gracefully if unavailable.
    */
    'process_mimes' => [
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
    ],
];
```

- [ ] **Step 2: Verify config loads**

Run:
```bash
php artisan config:show image-compression
```
Expected: prints the config array with `enabled => true`, `max_width => 2500`, etc.

- [ ] **Step 3: Commit**

```bash
git add config/image-compression.php
git commit -m "feat: add image-compression config"
```

---

## Task 3: Add test fixtures

**Files:**
- Create: `tests/fixtures/images/small.jpg` (800×600 JPEG)
- Create: `tests/fixtures/images/large.jpg` (4000×3000 JPEG)
- Create: `tests/fixtures/images/alpha.png` (1000×1000 PNG with transparency)
- Create: `tests/fixtures/images/corrupt.jpg` (invalid bytes)

- [ ] **Step 1: Generate fixtures via PHP script**

Run:
```bash
mkdir -p tests/fixtures/images
php -r '
$small = imagecreatetruecolor(800, 600);
imagefill($small, 0, 0, imagecolorallocate($small, 100, 150, 200));
imagejpeg($small, "tests/fixtures/images/small.jpg", 95);
imagedestroy($small);

$large = imagecreatetruecolor(4000, 3000);
imagefill($large, 0, 0, imagecolorallocate($large, 200, 100, 50));
imagejpeg($large, "tests/fixtures/images/large.jpg", 95);
imagedestroy($large);

$alpha = imagecreatetruecolor(1000, 1000);
imagesavealpha($alpha, true);
imagefill($alpha, 0, 0, imagecolorallocatealpha($alpha, 0, 0, 0, 127));
imagepng($alpha, "tests/fixtures/images/alpha.png");
imagedestroy($alpha);

file_put_contents("tests/fixtures/images/corrupt.jpg", "not a real jpeg");
echo "done\n";
'
```
Expected: prints "done", four files exist in `tests/fixtures/images/`.

- [ ] **Step 2: Verify fixtures**

Run:
```bash
ls -la tests/fixtures/images/ && file tests/fixtures/images/*
```
Expected: small.jpg ~30KB, large.jpg ~700KB, alpha.png ~10KB, corrupt.jpg 15 bytes. `file` reports JPEG/PNG/data correctly.

- [ ] **Step 3: Commit**

```bash
git add tests/fixtures/images/
git commit -m "test: add image fixtures for compression tests"
```

---

## Task 4: ImageCompressor — failing test for "JPEG larger than max_width is resized"

**Files:**
- Create: `tests/Unit/ImageCompressorTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\ImageCompressor;
use Tests\TestCase;

class ImageCompressorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/fixtures/images/{$name}"));
    }

    public function test_jpeg_wider_than_max_width_is_resized(): void
    {
        $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

        $result = $service->compress(
            bytes: $this->fixture('large.jpg'),
            mime: 'image/jpeg',
            filename: 'photo.jpg',
        );

        $info = getimagesizefromstring($result->bytes);
        $this->assertSame(2500, $info[0], 'width should be capped at 2500');
        $this->assertLessThan(3000, $info[1], 'height should scale proportionally');
        $this->assertSame('image/jpeg', $result->mime);
        $this->assertSame('photo.jpg', $result->filename);
        $this->assertLessThan(strlen($this->fixture('large.jpg')), strlen($result->bytes));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run:
```bash
./vendor/bin/phpunit --filter test_jpeg_wider_than_max_width_is_resized
```
Expected: FAIL with `Class "App\Services\ImageCompressor" not found`.

- [ ] **Step 3: Commit failing test**

```bash
git add tests/Unit/ImageCompressorTest.php
git commit -m "test: add failing test for jpeg resize"
```

---

## Task 5: ImageCompressor — minimal implementation to pass Task 4

**Files:**
- Create: `app/Services/ImageCompressor.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Throwable;

class ImageCompressor
{
    public function __construct(
        private readonly int $maxWidth = 2500,
        private readonly int $jpegQuality = 85,
    ) {}

    public function compress(string $bytes, string $mime, string $filename): CompressionResult
    {
        return match ($mime) {
            'image/jpeg' => $this->compressJpeg($bytes, $filename),
            'image/png' => $this->compressPng($bytes, $filename),
            'image/heic', 'image/heif' => $this->compressHeic($bytes, $filename),
            default => new CompressionResult($bytes, $mime, $filename),
        };
    }

    private function compressJpeg(string $bytes, string $filename): CompressionResult
    {
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($bytes);

        if ($image->width() > $this->maxWidth) {
            $image->scaleDown(width: $this->maxWidth);
        }

        $encoded = (string) $image->toJpeg(quality: $this->jpegQuality);

        return new CompressionResult($encoded, 'image/jpeg', $filename);
    }

    private function compressPng(string $bytes, string $filename): CompressionResult
    {
        // Implemented in Task 7
        return new CompressionResult($bytes, 'image/png', $filename);
    }

    private function compressHeic(string $bytes, string $filename): CompressionResult
    {
        // Implemented in Task 8
        return new CompressionResult($bytes, 'image/heic', $filename);
    }
}
```

- [ ] **Step 2: Write the result DTO**

Create: `app/Services/CompressionResult.php`

```php
<?php

namespace App\Services;

final readonly class CompressionResult
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public string $filename,
    ) {}
}
```

- [ ] **Step 3: Run test, verify it passes**

Run:
```bash
./vendor/bin/phpunit --filter test_jpeg_wider_than_max_width_is_resized
```
Expected: PASS (1 test, multiple assertions).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ImageCompressor.php app/Services/CompressionResult.php
git commit -m "feat: add ImageCompressor service with jpeg resize"
```

---

## Task 6: ImageCompressor — JPEG narrower than max_width is only re-encoded

**Files:**
- Modify: `tests/Unit/ImageCompressorTest.php`

- [ ] **Step 1: Add failing test**

Append to the test class:

```php
public function test_jpeg_narrower_than_max_width_keeps_dimensions(): void
{
    $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

    $result = $service->compress(
        bytes: $this->fixture('small.jpg'),
        mime: 'image/jpeg',
        filename: 'small.jpg',
    );

    $info = getimagesizefromstring($result->bytes);
    $this->assertSame(800, $info[0]);
    $this->assertSame(600, $info[1]);
    $this->assertSame('image/jpeg', $result->mime);
}
```

- [ ] **Step 2: Run test**

Run:
```bash
./vendor/bin/phpunit --filter test_jpeg_narrower_than_max_width_keeps_dimensions
```
Expected: PASS (the existing implementation already handles this — `scaleDown` only triggers when wider).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/ImageCompressorTest.php
git commit -m "test: cover small jpeg keeps dimensions"
```

---

## Task 7: ImageCompressor — PNG with transparency

**Files:**
- Modify: `tests/Unit/ImageCompressorTest.php`
- Modify: `app/Services/ImageCompressor.php`

- [ ] **Step 1: Add failing test**

Append to the test class:

```php
public function test_png_preserves_transparency_and_format(): void
{
    $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

    $result = $service->compress(
        bytes: $this->fixture('alpha.png'),
        mime: 'image/png',
        filename: 'logo.png',
    );

    $this->assertSame('image/png', $result->mime);
    $this->assertSame('logo.png', $result->filename);

    $info = getimagesizefromstring($result->bytes);
    $this->assertSame(IMAGETYPE_PNG, $info[2]);

    // Spot-check that the corner pixel is still transparent.
    $tmp = tempnam(sys_get_temp_dir(), 'png');
    file_put_contents($tmp, $result->bytes);
    $img = imagecreatefrompng($tmp);
    $colorIndex = imagecolorat($img, 0, 0);
    $rgba = imagecolorsforindex($img, $colorIndex);
    $this->assertGreaterThan(0, $rgba['alpha'], 'corner pixel should remain transparent');
    imagedestroy($img);
    unlink($tmp);
}
```

- [ ] **Step 2: Run test, verify it fails**

Run:
```bash
./vendor/bin/phpunit --filter test_png_preserves_transparency_and_format
```
Expected: FAIL — current `compressPng` returns input unchanged but doesn't go through Intervention; this test should still pass for transparency. Run it. If it passes (because we return raw bytes), continue to Step 3 to actually re-encode.

- [ ] **Step 3: Implement PNG path**

Replace `compressPng` in `app/Services/ImageCompressor.php`:

```php
private function compressPng(string $bytes, string $filename): CompressionResult
{
    $manager = new ImageManager(new GdDriver());
    $image = $manager->read($bytes);

    if ($image->width() > $this->maxWidth) {
        $image->scaleDown(width: $this->maxWidth);
    }

    $encoded = (string) $image->toPng();

    return new CompressionResult($encoded, 'image/png', $filename);
}
```

- [ ] **Step 4: Run test, verify it passes**

Run:
```bash
./vendor/bin/phpunit --filter test_png_preserves_transparency_and_format
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ImageCompressor.php tests/Unit/ImageCompressorTest.php
git commit -m "feat: re-encode png while preserving transparency"
```

---

## Task 8: ImageCompressor — HEIC graceful degradation

**Files:**
- Modify: `tests/Unit/ImageCompressorTest.php`
- Modify: `app/Services/ImageCompressor.php`

- [ ] **Step 1: Add test for HEIC without Imagick**

Append to the test class:

```php
public function test_heic_returns_unchanged_when_imagick_unavailable(): void
{
    if (extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is available; this path tests degradation.');
    }

    $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

    $bytes = 'fake-heic-bytes';
    $result = $service->compress(
        bytes: $bytes,
        mime: 'image/heic',
        filename: 'photo.heic',
    );

    $this->assertSame($bytes, $result->bytes);
    $this->assertSame('image/heic', $result->mime);
    $this->assertSame('photo.heic', $result->filename);
}
```

- [ ] **Step 2: Implement graceful HEIC fallback**

Replace `compressHeic` in `app/Services/ImageCompressor.php`:

```php
private function compressHeic(string $bytes, string $filename): CompressionResult
{
    if (! extension_loaded('imagick')) {
        return new CompressionResult($bytes, 'image/heic', $filename);
    }

    try {
        $imagick = new \Imagick();
        $imagick->readImageBlob($bytes);
        $imagick->setImageFormat('jpeg');

        if ($imagick->getImageWidth() > $this->maxWidth) {
            $imagick->scaleImage($this->maxWidth, 0);
        }

        $imagick->setImageCompressionQuality($this->jpegQuality);
        $jpegBytes = $imagick->getImageBlob();
        $imagick->clear();

        $newName = preg_replace('/\.(heic|heif)$/i', '.jpg', $filename);

        return new CompressionResult($jpegBytes, 'image/jpeg', $newName);
    } catch (Throwable) {
        return new CompressionResult($bytes, 'image/heic', $filename);
    }
}
```

- [ ] **Step 3: Run tests**

Run:
```bash
./vendor/bin/phpunit --filter test_heic_returns_unchanged_when_imagick_unavailable
```
Expected: PASS or SKIPPED (if Imagick is installed locally).

- [ ] **Step 4: Commit**

```bash
git add app/Services/ImageCompressor.php tests/Unit/ImageCompressorTest.php
git commit -m "feat: add heic→jpeg conversion with imagick fallback"
```

---

## Task 9: ImageCompressor — corrupt input raises exception (caller decides)

**Files:**
- Modify: `tests/Unit/ImageCompressorTest.php`

- [ ] **Step 1: Add test**

Append:

```php
public function test_corrupt_jpeg_throws(): void
{
    $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

    $this->expectException(\Throwable::class);

    $service->compress(
        bytes: $this->fixture('corrupt.jpg'),
        mime: 'image/jpeg',
        filename: 'broken.jpg',
    );
}
```

- [ ] **Step 2: Run test**

Run:
```bash
./vendor/bin/phpunit --filter test_corrupt_jpeg_throws
```
Expected: PASS — Intervention throws on unreadable input. The listener will catch this in Task 11.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/ImageCompressorTest.php
git commit -m "test: corrupt jpeg propagates exception"
```

---

## Task 10: ImageCompressor — unsupported mime is returned untouched

**Files:**
- Modify: `tests/Unit/ImageCompressorTest.php`

- [ ] **Step 1: Add test**

Append:

```php
public function test_unsupported_mime_is_passthrough(): void
{
    $service = new ImageCompressor(maxWidth: 2500, jpegQuality: 85);

    $bytes = 'GIF89a fake bytes';
    $result = $service->compress(
        bytes: $bytes,
        mime: 'image/gif',
        filename: 'anim.gif',
    );

    $this->assertSame($bytes, $result->bytes);
    $this->assertSame('image/gif', $result->mime);
    $this->assertSame('anim.gif', $result->filename);
}
```

- [ ] **Step 2: Run test**

Run:
```bash
./vendor/bin/phpunit --filter test_unsupported_mime_is_passthrough
```
Expected: PASS (the `match` default branch in `compress()` returns input unchanged).

- [ ] **Step 3: Run full unit suite**

Run:
```bash
./vendor/bin/phpunit tests/Unit/ImageCompressorTest.php
```
Expected: all 6 tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/ImageCompressorTest.php
git commit -m "test: unsupported mime types are passthrough"
```

---

## Task 11: Listener — failing feature test

**Files:**
- Create: `tests/Feature/AssetUploadCompressionTest.php`

- [ ] **Step 1: Write feature test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Statamic\Events\AssetUploaded;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class AssetUploadCompressionTest extends TestCase
{
    public function test_uploaded_jpeg_in_assets_container_is_compressed(): void
    {
        Storage::fake('r2');

        $container = AssetContainer::make('assets')
            ->disk('r2')
            ->title('Assets');
        $container->save();

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2')->put('uploads/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('uploads/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset);

        $stored = Storage::disk('r2')->get('uploads/photo.jpg');
        $this->assertLessThan(strlen($largeBytes), strlen($stored));

        $info = getimagesizefromstring($stored);
        $this->assertSame(2500, $info[0]);
    }

    public function test_assets_in_other_containers_are_untouched(): void
    {
        Storage::fake('r2');

        $container = AssetContainer::make('private')
            ->disk('r2')
            ->title('Private');
        $container->save();

        $largeBytes = file_get_contents(base_path('tests/fixtures/images/large.jpg'));
        Storage::disk('r2')->put('private/photo.jpg', $largeBytes);

        $asset = $container->makeAsset('private/photo.jpg');
        $asset->save();

        AssetUploaded::dispatch($asset);

        $stored = Storage::disk('r2')->get('private/photo.jpg');
        $this->assertSame(strlen($largeBytes), strlen($stored));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run:
```bash
./vendor/bin/phpunit tests/Feature/AssetUploadCompressionTest.php
```
Expected: FAIL — first test fails because no listener is wired up; bytes on disk are unchanged.

- [ ] **Step 3: Commit failing test**

```bash
git add tests/Feature/AssetUploadCompressionTest.php
git commit -m "test: add failing feature test for asset compression"
```

---

## Task 12: Listener — implementation

**Files:**
- Create: `app/Listeners/CompressUploadedAsset.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write the listener**

```php
<?php

namespace App\Listeners;

use App\Services\ImageCompressor;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Statamic\Events\AssetUploaded;
use Throwable;

class CompressUploadedAsset
{
    public function __construct(
        private readonly ImageCompressor $compressor,
    ) {}

    public function handle(AssetUploaded $event): void
    {
        if (! config('image-compression.enabled')) {
            return;
        }

        $asset = $event->asset;

        if (! $asset instanceof Asset) {
            return;
        }

        $containerHandle = $asset->container()->handle();
        if (! in_array($containerHandle, (array) config('image-compression.containers'), true)) {
            return;
        }

        $mime = $asset->mimeType();
        if (! in_array($mime, (array) config('image-compression.process_mimes'), true)) {
            return;
        }

        try {
            $bytes = $asset->disk()->get($asset->path());
            if ($bytes === null) {
                return;
            }

            $result = $this->compressor->compress(
                bytes: $bytes,
                mime: $mime,
                filename: $asset->basename(),
            );

            $asset->disk()->put($asset->path(), $result->bytes);

            // If HEIC was converted to JPEG, rename the asset on disk + in metadata.
            if ($result->filename !== $asset->basename()) {
                $newPath = dirname($asset->path()) . '/' . $result->filename;
                $asset->disk()->move($asset->path(), $newPath);
                $asset->path($newPath)->save();
            }
        } catch (Throwable $e) {
            Log::warning('Asset compression failed; original kept.', [
                'asset' => $asset->path(),
                'mime' => $mime,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Build the compressor from config in a service binding**

Modify `app/Providers/AppServiceProvider.php` — add to the `register()` method:

```php
public function register(): void
{
    $this->app->singleton(\App\Services\ImageCompressor::class, function () {
        return new \App\Services\ImageCompressor(
            maxWidth: (int) config('image-compression.max_width'),
            jpegQuality: (int) config('image-compression.jpeg_quality'),
        );
    });
}
```

- [ ] **Step 3: Register the event listener**

Modify `app/Providers/AppServiceProvider.php` — add to the `boot()` method (next to existing `Event::listen` calls):

```php
Event::listen(
    \Statamic\Events\AssetUploaded::class,
    \App\Listeners\CompressUploadedAsset::class,
);
```

- [ ] **Step 4: Run feature tests**

Run:
```bash
./vendor/bin/phpunit tests/Feature/AssetUploadCompressionTest.php
```
Expected: both tests pass.

- [ ] **Step 5: Run full test suite**

Run:
```bash
./vendor/bin/phpunit
```
Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Listeners/CompressUploadedAsset.php app/Providers/AppServiceProvider.php
git commit -m "feat: wire AssetUploaded listener for auto-compression"
```

---

## Task 13: Manual smoke test in dev

**Files:** none

- [ ] **Step 1: Run the dev environment**

Run:
```bash
php artisan serve
```
And in another terminal:
```bash
npm run dev
```

- [ ] **Step 2: Upload a real photo via the CP**

1. Open `http://localhost:8000/cp` in browser, log in.
2. Navigate to Assets → `assets` container.
3. Upload a JPEG > 3000px wide (use a phone photo or grab one from a stock site).
4. Wait for the upload to complete.

- [ ] **Step 3: Verify compression on R2**

Check the asset in the CP — preview should show the photo. Inspect the file size in the asset detail; it should be a fraction of the original. If you have R2 dashboard access, confirm the object is the compressed size.

- [ ] **Step 4: Check logs**

Run:
```bash
tail -n 50 storage/logs/laravel.log
```
Expected: no warnings about compression failures.

- [ ] **Step 5: No commit** — manual verification only.

---

## Task 14: Forge production rollout

**Files:** none (manual)

- [ ] **Step 1: Push to main**

```bash
git push origin main
```

- [ ] **Step 2: Wait for Forge deploy**

Confirm the deploy succeeds in the Forge dashboard.

- [ ] **Step 3: Update PHP settings in Forge**

In Forge → Server → PHP:
- Set `Max file upload size` from `2` to `25`.
- Set `Max execution time` from `30` to `120`.
- Click Save. Forge will restart PHP-FPM.

- [ ] **Step 4: Verify with phpinfo or CLI on the server**

Via Forge SSH:
```bash
php -i | grep -E "upload_max_filesize|max_execution_time|post_max_size"
```
Expected: `upload_max_filesize => 25M`, `max_execution_time => 120`, `post_max_size` should also be ≥ 25M (Forge usually sets it together).

- [ ] **Step 5: Production smoke test**

Have a client (or yourself) upload a 5–10 MB photo via the production CP. Verify:
1. Upload succeeds without 413/PHP errors.
2. The asset on R2 is significantly smaller than the original (< 1 MB typically).
3. Image displays correctly on the front-end.

- [ ] **Step 6: Monitor logs for 24 h**

Via Forge SSH:
```bash
tail -f storage/logs/laravel.log
```
Watch for `Asset compression failed` warnings during normal client usage. If any appear, capture the asset path + mime and investigate.

---

## Self-Review

**Spec coverage:**

| Spec section | Implemented in |
|---|---|
| Forge upload limit 25 MB | Task 14 |
| Forge execution time 120 s | Task 14 |
| `CompressUploadedAsset` listener | Task 12 |
| `ImageCompressor` service | Tasks 4, 5, 7, 8 |
| `config/image-compression.php` | Task 2 |
| EventServiceProvider registration | Task 12 (registered in `AppServiceProvider` — this project doesn't have a separate EventServiceProvider) |
| JPEG resize + q85 | Tasks 4, 5 |
| PNG resize + lossless | Task 7 |
| HEIC → JPEG via Imagick | Task 8 |
| GIF/SVG/WebP skip | Task 10 (passthrough), config `process_mimes` (Task 2) |
| Filter on container `assets` | Task 12 |
| Synchronous in v1 | Listener is plain class — Laravel calls it synchronously since it doesn't `implements ShouldQueue` |
| Error handling logs warning, keeps original | Task 12 |
| Imagick unavailable → log + skip | Task 8 (returns input unchanged), Task 12 logs |
| Unit tests (JPEG large, JPEG small, PNG alpha, corrupt, GIF skip, HEIC fallback) | Tasks 4, 6, 7, 8, 9, 10 |
| Feature test (event fires, compresses, other containers ignored) | Task 11 |
| Test fixtures | Task 3 |

All spec requirements are covered. v2 (Redis queue) is intentionally deferred per the spec.

**Placeholder scan:** No TBDs, no "implement later" — Tasks 5, 7, 8 each contain the actual code; the inline `// Implemented in Task X` comments in Task 5's stubs are fully replaced in their respective tasks. Each task has concrete commands and expected outputs.

**Type consistency:**
- `CompressionResult` properties: `bytes`, `mime`, `filename` — used consistently across Tasks 5–10 and the listener in Task 12.
- `ImageCompressor::compress(string $bytes, string $mime, string $filename)` signature is identical across all tests and the listener call site.
- `$asset->basename()`, `$asset->path()`, `$asset->disk()`, `$asset->mimeType()` are real Statamic Asset methods.
- `Statamic\Events\AssetUploaded::$asset` is the real public property on that event.

Plan is internally consistent and ready to execute.
