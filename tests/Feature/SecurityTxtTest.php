<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityTxtTest extends TestCase
{
    public function test_it_names_a_contact_and_an_expiry_on_the_path_rfc_9116_prescribes(): void
    {
        config()->set('app.url', 'https://voorbeeld.test');

        $body = $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('Contact: https://voorbeeld.test/contact', $body);
        $this->assertStringContainsString('Canonical: https://voorbeeld.test/.well-known/security.txt', $body);
        $this->assertMatchesRegularExpression('/^Expires: \d{4}-\d{2}-\d{2}T[\d:.]+Z$/m', $body);
    }

    /**
     * Een verlopen `Expires` maakt het bestand waardeloos: een onderzoeker hoort
     * er dan niet meer op te vertrouwen. Daarom wordt de datum berekend en niet
     * ingetypt.
     */
    public function test_the_expiry_lies_in_the_future(): void
    {
        preg_match('/^Expires: (\S+)$/m', $this->get('/.well-known/security.txt')->getContent(), $match);

        $this->assertTrue(
            strtotime($match[1] ?? '') > time(),
            'De security.txt is verlopen en daarmee ongeldig.'
        );
    }
}
