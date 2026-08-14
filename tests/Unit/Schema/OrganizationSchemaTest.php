<?php

namespace Tests\Unit\Schema;

use App\Schema\OrganizationSchema;
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
