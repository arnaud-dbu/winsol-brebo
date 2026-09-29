<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Tests\TestCase;

class FormMailAttachmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        Storage::fake('r2_private');
    }

    public function test_a_photo_on_the_repair_form_travels_along_in_the_mail(): void
    {
        $range = Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug();
        $location = Entry::query()->where('collection', 'locations')->first()->slug();

        $response = $this->withHeader('Content-Type', 'multipart/form-data')->post('/!/forms/herstelling', [
            'product' => $range,
            'is_winsol' => 'ja',
            'installed' => '2015',
            'facade' => 'voorgevel',
            'floor' => 'gelijkvloers',
            'dimensions' => '2m x 1m',
            'problem' => 'Zie foto',
            'branch' => $location,
            'photo' => [UploadedFile::fake()->image('rolluik.jpg', 800, 600)],
            'email' => 'klant@voorbeeld.be',
            'name' => 'Klant',
            'phone' => '+32 470 00 00 00',
            'address' => 'Teststraat 1, 1700 Dilbeek',
            'warranty' => 'nee',
            'rates_agreed' => '1',
            'warranty_terms' => '1',
            'gdpr' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages, 'Er vertrok geen (of meer dan één) mail.');

        $attachments = $messages->first()->getOriginalMessage()->getAttachments();
        $this->assertCount(1, $attachments, 'De foto zit niet als bijlage in de mail naar Winsol.');
        $this->assertSame('rolluik.jpg', $attachments[0]->getFilename());
    }

    public function test_a_photo_on_the_french_quote_form_travels_along_in_the_mail(): void
    {
        $range = Entry::query()->where('collection', 'ranges')->where('site', 'fr')->whereStatus('published')->first()->slug();
        $location = Entry::query()->where('collection', 'locations')->first()->slug();

        $response = $this->withHeader('Content-Type', 'multipart/form-data')->post('/!/forms/offerte', [
            'products' => [$range],
            'location' => $location,
            'name' => 'Poting',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'address' => 'Boomkwekerijstraat 1 Sint-Pieters-Leeuw, België',
            'project' => 'Je souhaite également la dépose de l’ancienne porte. Photo vue de l’intérieur',
            'attachment' => [UploadedFile::fake()->image('porte.jpg', 800, 600)],
            'gdpr' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages, 'Er vertrok geen (of meer dan één) mail.');

        $email = $messages->first()->getOriginalMessage();
        $this->assertStringContainsString('België', $email->getHtmlBody(), 'De accenten zijn uit het adres verdwenen.');
        $this->assertStringContainsString('l’intérieur', $email->getHtmlBody(), 'De accenten zijn uit het project verdwenen.');

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments, 'De foto zit niet als bijlage in de mail naar Winsol.');
        $this->assertSame('porte.jpg', $attachments[0]->getFilename());
    }
}
