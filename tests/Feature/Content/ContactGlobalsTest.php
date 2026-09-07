<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class ContactGlobalsTest extends TestCase
{
    /**
     * Alle aanvragen komen sinds 27-08-2026 op offertes@winsolspl.be binnen
     * (Jimmy via WhatsApp): dat is ook het adres dat de site zelf toont.
     *
     * De twee telefooncentrales stonden hier ook, als `phone_brussels` en
     * `phone_antwerp`. Ze zijn op 07-09-2026 naar de vestigingen verhuisd —
     * zie LocationPhonesTest — omdat Brussel en Antwerpen geen vestigingen
     * zijn en bezoekers uit Dilbeek en Sint-Pieters-Leeuw dat als een andere
     * streek lazen.
     */
    public function test_the_contact_details_carry_the_quote_address(): void
    {
        $contact = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact');

        $this->assertSame('offertes@winsolspl.be', $contact['email']);
        $this->assertArrayNotHasKey('phone_brussels', $contact);
        $this->assertArrayNotHasKey('phone_antwerp', $contact);
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
