<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class ContactGlobalsTest extends TestCase
{
    /**
     * Twee gescheiden centrales (Jimmy, werkoverleg 21/24-08): wie het
     * Brusselse 02-nummer belt komt bij de Brusselse verkoper terecht, wie
     * het Antwerpse 03-nummer belt bij de Antwerpse. Beide nummers zijn door
     * Quinten aangeleverd op 26-08; alle aanvragen komen sinds
     * 27-08-2026 op offertes@winsolspl.be binnen (Jimmy via WhatsApp): dat is
     * ook het adres dat de site zelf toont.
     */
    public function test_the_contact_details_carry_both_regional_numbers(): void
    {
        $contact = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact');

        $this->assertSame('+32 2 308 02 26', $contact['phone_brussels']);
        $this->assertSame('+32 3 880 85 65', $contact['phone_antwerp']);
        $this->assertSame('offertes@winsolspl.be', $contact['email']);
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
