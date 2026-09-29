<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Tekst in een bestandsveld liet de validatie van Statamic crashen:
 * `MimesRule` zoekt elke waarde die geen upload is op als asset, en
 * `Asset::find(null)` is een TypeError. Op live gaf dat sinds 18-09-2026
 * tientallen 500's, telkens 's nachts, dus vermoedelijk bots.
 */
class EmptyAssetFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        Storage::fake('r2_private');
    }

    public function test_an_empty_attachment_on_the_quote_form_is_treated_as_no_file(): void
    {
        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote(['attachment' => ['']]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_empty_photo_and_invoice_on_the_repair_form_are_treated_as_no_file(): void
    {
        $this->from('/herstelling')
            ->post('/!/forms/herstelling', $this->repair(['photo' => [''], 'invoice' => ['']]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_a_nested_value_in_a_file_field_does_not_crash(): void
    {
        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote(['attachment' => [['id' => 'x']]]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_an_empty_value_next_to_a_real_photo_keeps_the_photo(): void
    {
        $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', $this->quote([
                'attachment' => [UploadedFile::fake()->image('porte.jpg', 800, 600), ''],
            ]))
            ->assertSessionHasNoErrors();

        $attachments = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('porte.jpg', $attachments[0]->getFilename());
    }

    public function test_an_incomplete_submission_with_an_empty_attachment_is_rejected_not_crashed(): void
    {
        $this->from('/offerte')
            ->post('/!/forms/offerte', ['name' => 'Bot', 'attachment' => ['']])
            ->assertRedirect('/offerte')
            ->assertSessionHasErrors(['email'], errorBag: 'form.offerte');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quote(array $overrides): array
    {
        return [
            'products' => [Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug()],
            'location' => Entry::query()->where('collection', 'locations')->first()->slug(),
            'name' => 'Klant',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'address' => 'Teststraat 1, 1700 Dilbeek',
            'project' => 'Een nieuwe voordeur',
            'gdpr' => '1',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function repair(array $overrides): array
    {
        return [
            'product' => Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug(),
            'is_winsol' => 'ja',
            'installed' => '2015',
            'facade' => 'voorgevel',
            'floor' => 'gelijkvloers',
            'dimensions' => '2m x 1m',
            'problem' => 'Zie foto',
            'branch' => Entry::query()->where('collection', 'locations')->first()->slug(),
            'email' => 'klant@voorbeeld.be',
            'name' => 'Klant',
            'phone' => '+32 470 00 00 00',
            'address' => 'Teststraat 1, 1700 Dilbeek',
            'warranty' => 'nee',
            'rates_agreed' => '1',
            'warranty_terms' => '1',
            'gdpr' => '1',
            ...$overrides,
        ];
    }
}
