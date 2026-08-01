<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class ContactGlobalsTest extends TestCase
{
    /**
     * De waarden uit het ontwerp waren onbetrouwbaar (`03 000 00 00`,
     * `info@winsolbrebo.be`); deze komen van winsoldilbeek.be. Dilbeek is het
     * hoofdverkooppunt, dus zijn nummer en adres staan in de globals. De twee
     * andere filialen hebben een eigen nummer en staan in de
     * locations-collectie.
     */
    public function test_the_contact_details_come_from_the_real_site(): void
    {
        $contact = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact');

        $this->assertSame('+32 2 308 02 26', $contact['phone']);
        $this->assertSame('info@winsoldilbeek.be', $contact['email']);
    }

    public function test_the_company_address_is_the_dilbeek_branch(): void
    {
        $company = GlobalSet::findByHandle('globals')->inDefaultSite()->get('company');

        $this->assertSame('Ninoofsesteenweg', $company['street']);
        $this->assertSame('637', $company['number']);
        $this->assertSame('1700', $company['postal']);
        $this->assertSame('Dilbeek', $company['city']);
    }

    /**
     * wa.me accepteert alleen cijfers in internationaal formaat: geen +, geen
     * spaties, geen voorloopnul. Een nationale `0470 …` zou na de strip in
     * contactDetails een ongeldige wa.me/0470000000 opleveren.
     *
     * Het veld staat nu leeg, want er is geen echt nummer bekend en een
     * verzonnen nummer belt bij een vreemde aan. Leeg laat de partial de
     * WhatsApp-knop overslaan. Zodra Jimmy een nummer geeft, bewaakt deze test
     * het formaat.
     */
    public function test_the_mobile_number_is_empty_or_survives_the_strip_that_wa_me_needs(): void
    {
        $mobile = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact')['mobile'];

        if ($mobile === '' || $mobile === null) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertMatchesRegularExpression('/^32\d{8,9}$/', str_replace(['+', ' '], '', $mobile));
    }
}
