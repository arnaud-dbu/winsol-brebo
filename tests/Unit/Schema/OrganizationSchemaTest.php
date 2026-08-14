<?php

namespace Tests\Unit\Schema;

use App\Schema\OrganizationSchema;
use Mockery;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as VariablesContract;
use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    public function test_the_node_uses_the_site_name_and_the_shared_phone_number(): void
    {
        $node = OrganizationSchema::node();

        $this->assertSame('Organization', $node['@type']);
        $this->assertSame('Winsol Brebo', $node['name']);
        $this->assertSame('+32 2 308 02 26', $node['telephone']);
        $this->assertStringEndsWith('/#organization', $node['@id']);
    }

    /**
     * `sameAs()` zelf is elders getest, maar niet of `node()` hem ook echt
     * voedt met `socials` in plaats van `contact`. Beide arrays krijgen hier
     * een geldige maar verschillende facebook-URL, zodat een verwisseling
     * (`self::sameAs($contact)`) een andere waarde oplevert en de test rood
     * maakt in plaats van gewoon te slagen omdat allebei "iets" teruggeven.
     */
    public function test_the_node_wires_sameas_to_socials_and_not_to_contact(): void
    {
        $variables = Mockery::mock(VariablesContract::class);
        $variables->shouldReceive('get')->with('contact')->andReturn([
            'phone' => '+32 2 000 00 00',
            'email' => 'info@example.test',
            'facebook' => 'https://facebook.com/verkeerde-bron',
        ]);
        $variables->shouldReceive('get')->with('socials')->andReturn([
            'facebook' => 'https://www.facebook.com/winsolbrebo',
        ]);

        $globalSet = Mockery::mock(GlobalSetContract::class);
        $globalSet->shouldReceive('inCurrentSite')->andReturn($variables);

        GlobalSet::shouldReceive('findByHandle')->with('globals')->andReturn($globalSet);

        $node = OrganizationSchema::node();

        $this->assertSame(['https://www.facebook.com/winsolbrebo'], $node['sameAs']);
    }

    /**
     * globals.socials staat op https://test.be. Placeholders in sameAs zijn
     * schadelijker dan een ontbrekend veld, dus ze mogen er niet in.
     */
    public function test_placeholder_socials_are_dropped(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs([
            'facebook' => 'https://test.be',
            'instagram' => 'https://test.be',
            'linkedin' => 'https://test.be',
            'youtube' => 'https://test.be',
        ]));
    }

    public function test_a_real_platform_url_is_kept(): void
    {
        $this->assertSame(
            ['https://www.facebook.com/winsolbrebo'],
            OrganizationSchema::sameAs(['facebook' => 'https://www.facebook.com/winsolbrebo']),
        );
    }

    public function test_a_url_on_the_wrong_platform_host_is_dropped(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs([
            'facebook' => 'https://instagram.com/winsolbrebo',
        ]));
    }

    public function test_youtu_be_counts_as_youtube(): void
    {
        $this->assertSame(
            ['https://youtu.be/abc123'],
            OrganizationSchema::sameAs(['youtube' => 'https://youtu.be/abc123']),
        );
    }

    public function test_empty_socials_yield_nothing(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs(['facebook' => '', 'linkedin' => null]));
    }
}
