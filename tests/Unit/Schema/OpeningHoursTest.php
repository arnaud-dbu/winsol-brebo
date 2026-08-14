<?php

namespace Tests\Unit\Schema;

use App\Schema\OpeningHours;
use PHPUnit\Framework\TestCase;

class OpeningHoursTest extends TestCase
{
    public function test_a_day_range_expands_to_every_day_in_it(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
        ]);

        $this->assertCount(1, $specs);
        $this->assertSame(
            ['Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $specs[0]['dayOfWeek'],
        );
        $this->assertSame('10:30', $specs[0]['opens']);
        $this->assertSame('17:30', $specs[0]['closes']);
    }

    public function test_a_single_day_is_not_wrapped_in_an_array(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
        ]);

        $this->assertSame('Saturday', $specs[0]['dayOfWeek']);
    }

    public function test_gesloten_becomes_the_documented_zero_range(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Zondag', 'time' => 'Gesloten'],
        ]);

        $this->assertSame('Sunday', $specs[0]['dayOfWeek']);
        $this->assertSame('00:00', $specs[0]['opens']);
        $this->assertSame('00:00', $specs[0]['closes']);
    }

    /**
     * Schema.org kent geen "op afspraak", en een specificatie zonder
     * opens/closes is ongeldig. Weglaten is dan beter dan gokken.
     */
    public function test_op_afspraak_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'Op afspraak'],
        ]));
    }

    public function test_an_unreadable_time_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'van 9 tot 5'],
        ]));
    }

    public function test_an_unknown_day_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Someday', 'time' => '10:00 - 16:00'],
        ]));
    }

    public function test_a_reversed_range_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Vr - Di', 'time' => '10:00 - 16:00'],
        ]));
    }

    public function test_the_real_winsol_week_produces_three_specifications(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'Op afspraak'],
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
            ['day' => 'Zondag', 'time' => 'Gesloten'],
        ]);

        $this->assertCount(3, $specs);
        $this->assertSame('OpeningHoursSpecification', $specs[0]['@type']);
    }
}
