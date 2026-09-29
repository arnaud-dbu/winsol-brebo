<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Een geweigerde inzending laat verder geen spoor na: Statamic slaat niets op
 * en verstuurt niets. Bij de aanvraag van Poting (25-09-2026) viel daardoor
 * achteraf niet meer na te gaan of zijn foto bij een eerste, geweigerde poging
 * zat.
 */
class LogRejectedFormSubmissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        Storage::fake('r2_private');
    }

    public function test_a_rejected_submission_is_logged_with_its_fields_and_files(): void
    {
        Log::spy();

        $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', [
                'name' => 'Poting',
                'attachment' => [UploadedFile::fake()->image('porte.jpg', 800, 600)],
            ])
            ->assertSessionHasErrors();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Inzending geweigerd bij de validatie.'
                    && $context['formulier'] === 'offerte'
                    && in_array('email', $context['velden'], true)
                    && ! in_array('name', $context['velden'], true)
                    && $context['bestanden'] === 1;
            })
            ->once();
    }

    public function test_the_log_holds_no_values_from_the_visitor(): void
    {
        Log::spy();

        $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', ['name' => 'Poting', 'email' => 'geen-adres'])
            ->assertSessionHasErrors();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'Poting')
                && ! str_contains(json_encode($context), 'geen-adres'))
            ->once();
    }

    public function test_an_accepted_submission_is_not_logged(): void
    {
        Log::spy();

        $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', [
                'products' => [Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug()],
                'location' => Entry::query()->where('collection', 'locations')->first()->slug(),
                'name' => 'Poting',
                'phone' => '+32 470 00 00 00',
                'email' => 'klant@voorbeeld.be',
                'address' => 'Teststraat 1, 1700 Dilbeek',
                'project' => 'Een nieuwe voordeur',
                'gdpr' => '1',
            ])
            ->assertSessionHasNoErrors();

        Log::shouldNotHaveReceived('warning', fn (string $message): bool => $message === 'Inzending geweigerd bij de validatie.');
    }
}
