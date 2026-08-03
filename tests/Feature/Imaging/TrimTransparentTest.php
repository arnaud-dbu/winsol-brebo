<?php

namespace Tests\Feature\Imaging;

use App\Imaging\AlphaBounds;
use App\Imaging\Manipulators\TrimTransparent;
use League\Glide\Manipulators\Size;
use League\Glide\Server;
use Tests\TestCase;

class TrimTransparentTest extends TestCase
{
    /**
     * Een canvas met een dekkende rechthoek erin, op de opgegeven plek.
     */
    private function png(int $canvas, int $left, int $top, int $width, int $height): string
    {
        $image = imagecreatetruecolor($canvas, $canvas);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $canvas - 1, $canvas - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle(
            $image,
            $left,
            $top,
            $left + $width - 1,
            $top + $height - 1,
            imagecolorallocatealpha($image, 0, 0, 0, 0)
        );

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean();
    }

    public function test_it_finds_the_bounds_of_the_visible_pixels(): void
    {
        $image = imagecreatefromstring($this->png(canvas: 400, left: 60, top: 100, width: 200, height: 120));

        $bounds = AlphaBounds::scan($image);

        // De scan bemonstert met een stap en rekt daarna naar buiten op, dus
        // het gevonden vlak omsluit het echte vlak en snijdt het nooit aan.
        $this->assertLessThanOrEqual(60, $bounds->left);
        $this->assertLessThanOrEqual(100, $bounds->top);
        $this->assertGreaterThanOrEqual(200, $bounds->width);
        $this->assertGreaterThanOrEqual(120, $bounds->height);

        // En blijft er strak omheen: hooguit een paar pixel ruimer.
        $this->assertLessThan(210, $bounds->width);
        $this->assertLessThan(130, $bounds->height);

        $this->assertSame(400, $bounds->canvasWidth);
        $this->assertFalse($bounds->coversWholeCanvas());
    }

    public function test_a_fully_opaque_image_covers_the_whole_canvas(): void
    {
        $image = imagecreatefromstring($this->png(canvas: 200, left: 0, top: 0, width: 200, height: 200));

        $this->assertTrue(AlphaBounds::scan($image)->coversWholeCanvas());
    }

    public function test_a_fully_transparent_image_has_no_bounds(): void
    {
        $image = imagecreatetruecolor(50, 50);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 49, 49, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $this->assertNull(AlphaBounds::scan($image));
    }

    /**
     * De kern van deze bewerking. `trim=1` in de url bewijst niets: als Glide
     * de parameter wegfiltert of de manipulator niet in de keten hangt, komt
     * het volle canvas terug terwijl de header rekent met een bijgesneden
     * beeld. Het product staat dan te klein en op de verkeerde plek, zonder
     * dat er iets rood wordt.
     */
    public function test_the_manipulator_actually_crops_through_the_glide_server(): void
    {
        $source = $this->png(canvas: 400, left: 100, top: 50, width: 200, height: 300);

        $trimmed = imagecreatefromstring(
            app(Server::class)->getApi()->run($source, ['trim' => 1, 'fm' => 'png'])
        );

        $this->assertLessThan(400, imagesx($trimmed));
        $this->assertGreaterThanOrEqual(200, imagesx($trimmed));
        $this->assertLessThan(215, imagesx($trimmed));
        $this->assertGreaterThanOrEqual(300, imagesy($trimmed));
        $this->assertLessThan(315, imagesy($trimmed));
    }

    public function test_it_leaves_the_image_alone_without_the_parameter(): void
    {
        $source = $this->png(canvas: 400, left: 100, top: 50, width: 200, height: 300);

        $untouched = imagecreatefromstring(
            app(Server::class)->getApi()->run($source, ['fm' => 'png'])
        );

        $this->assertSame(400, imagesx($untouched));
        $this->assertSame(400, imagesy($untouched));
    }

    /**
     * Glide filtert zowel de bewerking als het cachepad op de lijst die de
     * manipulators samen opleveren. Staat `trim` daar niet in, dan doet de
     * parameter niets én delen een bijgesneden en een niet-bijgesneden variant
     * hetzelfde cachepad.
     */
    public function test_glide_knows_the_trim_parameter(): void
    {
        $this->assertContains('trim', app(Server::class)->getApi()->getApiParams());
    }

    /**
     * De volgorde is niet vrijblijvend: staat de trim ná `Size`, dan slaat `w`
     * op het canvas en wordt het bijgesneden product daarna opgeblazen.
     */
    public function test_the_trim_runs_before_the_resize(): void
    {
        $manipulators = app(Server::class)->getApi()->getManipulators();

        $positions = [];
        foreach ($manipulators as $index => $manipulator) {
            if ($manipulator instanceof TrimTransparent) {
                $positions['trim'] = $index;
            }
            if ($manipulator instanceof Size) {
                $positions['size'] = $index;
            }
        }

        $this->assertArrayHasKey('trim', $positions, 'De trim-manipulator hangt niet in de keten.');
        $this->assertLessThan($positions['size'], $positions['trim']);
    }

    /**
     * De alpha-scan leest GD rechtstreeks uit. Draait Glide op imagick, dan
     * laat de manipulator het beeld ongemoeid — geen zichtbare fout, maar wel
     * een header die op lucht uitlijnt. Vandaar dat de driver hier vastligt.
     */
    public function test_the_manipulation_driver_is_gd(): void
    {
        $this->assertSame('gd', config('statamic.assets.image_manipulation.driver'));
    }
}
