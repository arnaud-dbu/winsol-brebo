<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class ContactGlobalsTest extends TestCase
{
    public function test_the_contact_details_from_the_design_are_filled_in(): void
    {
        $contact = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact');

        $this->assertSame('+32 470 00 00 00', $contact['mobile']);
        $this->assertSame('03 000 00 00', $contact['phone']);
        $this->assertSame('info@winsolbrebo.be', $contact['email']);
    }

    public function test_the_mobile_number_survives_the_strip_that_wa_me_needs(): void
    {
        // wa.me accepteert alleen cijfers in internationaal formaat: geen +,
        // geen spaties, geen voorloopnul. Daarom staat `mobile` internationaal
        // genoteerd — een nationale `0470 …` zou na de strip een ongeldige
        // wa.me/0470000000 opleveren. Dit pint dat formaatcontract vast, want
        // de partial in taak 2 leunt erop.
        $mobile = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact')['mobile'];

        $this->assertSame('32470000000', str_replace(['+', ' '], '', $mobile));
    }
}
