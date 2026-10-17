<?php

namespace Tests\Feature;

use App\Services\FormTimer;
use App\Services\Leadbot\DemoStore;
use App\Services\Leadbot\FormSchema;
use App\Services\Leadbot\Knowledge;
use App\Services\Leadbot\WidgetConfig;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\Entry;
use Statamic\Facades\Form;
use Statamic\Forms\Submission;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * De website-assistent. Staat standaard uit en laat dan geen sporen na. Aan
 * beantwoordt hij vragen uit de site, neemt hij de formulieren af met de
 * velden uit hun blueprint en post hij naar hetzelfde endpoint als de pagina,
 * waarbij het gesprek met de inzending meereist.
 */
class LeadbotTest extends TestCase
{
    use PassesFormTimer;

    private string $statePath;

    private ?Submission $stored = null;

    /** @var array<int, string> */
    private array $createdEntries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = tempnam(sys_get_temp_dir(), 'leadbot');
        unlink($this->statePath);
        $this->app->instance(DemoStore::class, new DemoStore($this->statePath));
        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'leadbot.enabled' => true,
            'leadbot.demo_key' => 'geheim',
            'leadbot.anthropic.key' => null,
            'mail.default' => 'array',
            'services.recaptcha.api_key' => null,
            'services.recaptcha.project_id' => null,
            'services.recaptcha.site_key' => null,
        ]);

        Storage::fake('r2_private');
        Cache::forget(Knowledge::CACHE_KEY);
        Event::listen(SubmissionCreated::class, fn (SubmissionCreated $event) => $this->stored = $event->submission);
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        $this->stored?->delete();

        foreach ($this->createdEntries as $id) {
            Entry::find($id)?->delete();
        }

        parent::tearDown();
    }

    public function test_it_is_invisible_when_disabled(): void
    {
        config(['leadbot.enabled' => false]);

        $this->getJson('/leadbot/session')->assertNotFound();
        $this->postJson('/leadbot/ask', ['question' => 'Is de offerte gratis?', 'locale' => 'nl'])->assertNotFound();
        $this->get('/leadbot/demo?key=geheim')->assertNotFound();
    }

    public function test_the_layout_only_loads_the_widget_when_enabled(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertMatchesRegularExpression('/{{ if leadbot_enabled }}\s*{{ partial:leadbot }}\s*{{ \/if }}/', $layout);
    }

    public function test_the_widget_config_carries_the_four_forms_in_the_visitors_language(): void
    {
        $config = json_decode(app(WidgetConfig::class)->json('fr'), true);

        $this->assertSame('fr', $config['locale']);
        $this->assertSame(['offerte', 'herstelling', 'brochure', 'contact'], array_keys($config['forms']));
        $this->assertSame('/!/forms/offerte', $config['forms']['offerte']['action']);
        $this->assertStringStartsWith('/fr/', $config['ranges'][0]['url']);
        $this->assertCount(3, $config['showrooms']);
        $this->assertSame('appointment', collect($config['showrooms'])->firstWhere('slug', 'winsol-dilbeek')['schedule'][1]);
    }

    public function test_every_field_of_a_form_blueprint_becomes_a_step(): void
    {
        $schema = app(FormSchema::class)->form('herstelling', 'nl');
        $fields = collect($schema['steps'])->flatten(1)->keyBy('handle');

        $blueprint = Form::find('herstelling')->blueprint()->fields()->all()->keys()->reject(fn ($handle) => $handle === 'language');
        $this->assertEqualsCanonicalizing($blueprint->values()->all(), $fields->keys()->all());

        $this->assertSame('choice', $fields['facade']['kind']);
        $this->assertSame(['voorgevel', 'achtergevel', 'zijgevel'], array_column($fields['facade']['options'], 'value'));
        $this->assertTrue($fields['installed']['year']);
        $this->assertSame([-2, 30], [$fields['floor']['min'], $fields['floor']['max']]);
        $this->assertSame('files', $fields['photo']['kind']);
        $this->assertSame(5, $fields['photo']['max_files']);
        $this->assertSame('consent', $fields['rates_agreed']['kind']);

        $consents = collect($schema['steps'])->first(fn ($step) => collect($step)->pluck('handle')->contains('rates_agreed'));
        $this->assertSame(['rates_agreed', 'warranty_terms', 'gdpr'], array_column($consents, 'handle'));
    }

    public function test_the_knowledge_finds_dutch_pages_for_a_french_question(): void
    {
        $results = collect(app(Knowledge::class)->search('Quelle garantie ai-je ?', 'fr', 5));

        $this->assertContains('faq:garantie', $results->pluck('id'));
        $this->assertSame('Quelle garantie ai-je ?', $results->firstWhere('id', 'faq:garantie')['title']);
    }

    public function test_without_a_model_it_answers_from_the_faq(): void
    {
        $this->postJson('/leadbot/ask', ['question' => 'Betaal ik 6 of 21 procent btw bij renovatie?', 'locale' => 'nl'])
            ->assertOk()
            ->assertJson(['answered' => true, 'mode' => 'fallback'])
            ->assertJsonPath('answer', fn ($answer) => str_contains($answer, '6 procent'));
    }

    public function test_without_a_model_it_still_advises_on_a_choice(): void
    {
        $this->postJson('/leadbot/ask', ['question' => 'Welke rolluiken moet ik kiezen?', 'locale' => 'nl'])
            ->assertOk()
            ->assertJson(['answered' => true, 'next_action' => 'offerte', 'range' => 'rolluiken'])
            ->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'Voorzetrolluiken'));
    }

    public function test_an_unanswered_question_lands_in_the_open_questions(): void
    {
        $this->postJson('/leadbot/ask', ['question' => 'Verkopen jullie ook tuinhuizen?', 'locale' => 'nl'])
            ->assertOk()
            ->assertJson(['answered' => false]);

        $this->assertSame('Verkopen jullie ook tuinhuizen?', collect(app(DemoStore::class)->state()['questions'])->last()['question']);
    }

    public function test_with_a_model_it_sends_the_best_sources_and_maps_links_to_the_visitors_language(): void
    {
        config(['leadbot.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode([
                'answer' => 'Pour une terrasse, je vous conseille une tente solaire.',
                'source_ids' => ['ranges:zonwering', 'bestaat:niet'],
                'answered' => true,
                'next_action' => 'offerte',
                'range_handle' => 'zonwering',
            ])]],
        ])]);

        $this->postJson('/leadbot/ask', ['question' => 'Quelle protection solaire pour ma terrasse ?', 'locale' => 'fr', 'mode' => 'advice'])
            ->assertOk()
            ->assertJson(['mode' => 'model', 'next_action' => 'offerte'])
            ->assertJsonPath('sources.0.url', fn ($url) => str_starts_with($url, '/fr/'));

        Http::assertSent(function (HttpRequest $request) {
            $last = collect($request['messages'])->last()['content'];

            return $request->hasHeader('x-api-key', 'test-key')
                && $request['output_config']['format']['type'] === 'json_schema'
                && $request['system'][0]['cache_control']['type'] === 'ephemeral'
                && str_contains($request['system'][0]['text'], 'adviseur')
                && str_contains($last, '<bron id="ranges:zonwering"')
                && str_contains($last, 'Frans')
                && str_contains($last, 'concreet advies');
        });
    }

    public function test_a_failing_model_falls_back_instead_of_breaking_the_widget(): void
    {
        config(['leadbot.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);

        $this->postJson('/leadbot/ask', ['question' => 'Is de offerte gratis?', 'locale' => 'nl'])
            ->assertOk()
            ->assertJson(['mode' => 'fallback']);
    }

    public function test_prefilling_keeps_only_values_the_form_allows(): void
    {
        config(['leadbot.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode([
                'product' => 'rolluiken',
                'facade' => 'dakgevel',
                'problem' => 'Het rolluik hangt vast.',
                'floor' => '',
            ])]],
        ])]);

        $this->postJson('/leadbot/extract', ['form' => 'herstelling', 'text' => 'Mijn rolluik op de dakgevel hangt vast', 'locale' => 'nl'])
            ->assertOk()
            ->assertExactJson(['values' => ['product' => 'rolluiken', 'problem' => 'Het rolluik hangt vast.']]);
    }

    public function test_the_session_gives_a_token_and_a_load_time_the_spam_filter_accepts(): void
    {
        $session = $this->getJson('/leadbot/session')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

        $this->assertNotEmpty($session['token']);
        $this->assertNotNull(FormTimer::secondsSinceLoad($session['loaded']));
    }

    public function test_a_submission_through_the_assistant_carries_the_conversation_into_the_mail_and_the_erp(): void
    {
        $this->from('/aanbod/zonwering')->post('/!/forms/offerte', [
            'products' => ['zonwering'],
            'location' => 'winsol-dilbeek',
            'name' => 'An Peeters',
            'phone' => '0470 12 34 56',
            'email' => 'an@voorbeeld.be',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'project' => 'Een zonnescherm voor het terras.',
            'gdpr' => '1',
            ...$this->formTimer(),
            '_leadbot' => json_encode([
                'transcript' => [
                    ['role' => 'bot', 'text' => 'Waarvoor wil je een offerte?'],
                    ['role' => 'user', 'text' => 'Een zonnescherm voor het terras.'],
                ],
                'page' => 'https://winsol-brebo.be/aanbod/zonwering',
                'tracking' => ['gclid' => 'abc123', 'onbekend' => 'weg'],
                'locale' => 'nl',
            ]),
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['success' => true, 'submission_created' => true]);

        $data = $this->stored->data()->all();
        $this->assertSame('Website-assistent', $data['via']);
        $this->assertStringContainsString('Klant: Een zonnescherm voor het terras.', $data['assistant_transcript']);
        $this->assertSame(['gclid' => 'abc123'], $data['assistant_tracking']);
        $this->assertNotEmpty($data['assistant_summary']);

        $request = collect(app(DemoStore::class)->state()['requests'])->last();
        $this->assertSame('Website-assistent', $request['source']);
        $this->assertSame(['Zonwering'], $request['products']);
        $this->assertSame('winsol-dilbeek', $request['showroom']);
        $this->assertCount(2, $request['transcript']);

        $mail = view('emails/notification', [...$data, 'fields' => [], 'email_config' => ['label' => 'Offerteaanvraag']])->render();
        $this->assertStringContainsString('Via de website-assistent', $mail);
        $this->assertStringContainsString('Google Ads', $mail);
        $this->assertStringContainsString('Klant: Een zonnescherm voor het terras.', $mail);
    }

    public function test_a_regular_form_submission_stays_untouched_but_shows_up_in_the_erp(): void
    {
        $this->from('/offerte')->post('/!/forms/offerte', [
            'products' => ['zonwering'],
            'location' => 'winsol-dilbeek',
            'name' => 'An Peeters',
            'phone' => '0470 12 34 56',
            'email' => 'an@voorbeeld.be',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'project' => 'Een zonnescherm.',
            'gdpr' => '1',
            ...$this->formTimer(),
        ]);

        $this->assertArrayNotHasKey('via', $this->stored->data()->all());
        $this->assertSame('Website-formulier', collect(app(DemoStore::class)->state()['requests'])->last()['source']);
    }

    public function test_the_assistant_cannot_slip_past_the_spam_filter(): void
    {
        $this->from('/offerte')->post('/!/forms/offerte', [
            'products' => ['zonwering'],
            'location' => 'winsol-dilbeek',
            'name' => 'Bot www.spam.ru',
            'phone' => '0470 12 34 56',
            'email' => 'bot@voorbeeld.be',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'project' => 'Spam.',
            'gdpr' => '1',
            ...$this->formTimer(),
            '_leadbot' => json_encode(['transcript' => [], 'locale' => 'nl']),
        ], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->assertJson(['submission_created' => false]);

        $this->assertNull($this->stored);
    }

    public function test_the_demo_screen_needs_the_key(): void
    {
        $this->get('/leadbot/demo')->assertNotFound();
        $this->get('/leadbot/demo?key=fout')->assertNotFound();
        $this->get('/leadbot/demo?key=geheim')->assertOk()->assertSee('Requests');
        $this->getJson('/leadbot/demo/state?key=geheim')->assertOk()->assertJsonStructure(['requests', 'questions', 'statuses', 'categories']);
    }

    public function test_answering_an_open_question_publishes_it_as_faq(): void
    {
        $question = collect(app(DemoStore::class)->state()['questions'])->first();

        $this->postJson("/leadbot/demo/questions/{$question['id']}?key=geheim", ['answer' => 'Ja, vliegenramen plaatsen we ook.', 'category' => 'producten'])
            ->assertOk();

        $entry = Entry::query()->where('collection', 'faq')->where('title', $question['question'])->first();
        $this->createdEntries[] = $entry->id();

        $this->assertSame('assistent', $entry->get('source'));
        $this->assertTrue($entry->published());
        $this->assertContains('Ja, vliegenramen plaatsen we ook.', array_column(app(Knowledge::class)->faq('nl'), 'answer'));
    }

    public function test_the_faq_import_adds_new_questions_and_skips_known_ones(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'faq').'.csv';
        file_put_contents($csv, "question;answer;category\nLeveren jullie ook in Brussel?;Ja, vanuit Dilbeek.;showroom\nIs een offerte gratis?;Dubbel;offerte\n");

        $this->artisan('leadbot:import-faq', ['file' => $csv, '--draft' => true])
            ->expectsOutputToContain('Geïmporteerd: 1, overgeslagen: 1')
            ->assertSuccessful();

        $entry = Entry::query()->where('collection', 'faq')->where('title', 'Leveren jullie ook in Brussel?')->first();
        $this->createdEntries[] = $entry->id();

        $this->assertFalse($entry->published());
        $this->assertSame('telavox', $entry->get('source'));
        unlink($csv);
    }

    public function test_transcripts_become_faq_drafts(): void
    {
        config(['leadbot.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode(['items' => [
                ['question' => 'Kunnen jullie een screen op een schuifraam plaatsen?', 'answer' => 'Ja, ook op een schuifraam.', 'category' => 'producten'],
            ]])]],
        ])]);

        $folder = sys_get_temp_dir().'/telavox-'.uniqid();
        mkdir($folder);
        file_put_contents("{$folder}/gesprek-1.txt", 'Klant: kan dat op een schuifraam? Medewerker: ja, zeker.');

        $this->artisan('leadbot:faq-from-transcripts', ['path' => $folder])->assertSuccessful();

        $entry = Entry::query()->where('collection', 'faq')->where('title', 'Kunnen jullie een screen op een schuifraam plaatsen?')->first();
        $this->createdEntries[] = $entry->id();

        $this->assertFalse($entry->published());
        unlink("{$folder}/gesprek-1.txt");
        rmdir($folder);
    }
}
