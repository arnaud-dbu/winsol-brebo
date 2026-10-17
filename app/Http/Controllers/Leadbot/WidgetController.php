<?php

namespace App\Http\Controllers\Leadbot;

use App\Http\Controllers\Controller;
use App\Services\FormTimer;
use App\Services\Leadbot\Assistant;
use App\Services\Leadbot\DemoStore;
use App\Services\Leadbot\Knowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WidgetController extends Controller
{
    public function __construct(
        private Assistant $assistant,
        private Knowledge $knowledge,
        private DemoStore $store,
    ) {}

    /**
     * Wat de widget nodig heeft om een formulier te posten zoals de pagina
     * dat doet: een CSRF-token voor deze sessie, de versleutelde laadtijd
     * voor RejectSpam en de reCAPTCHA-sleutel als die er is. Niet in de
     * pagina zelf, want die komt uit de statische cache en wordt gedeeld.
     */
    public function session(): JsonResponse
    {
        return response()
            ->json([
                'token' => csrf_token(),
                'loaded' => FormTimer::stamp(),
                'recaptcha' => config('services.recaptcha.site_key') ?: null,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function ask(Request $request): JsonResponse
    {
        $data = $this->validateQuestion($request);

        $mode = $data['mode'] ?? 'answer';
        $result = $this->assistant->answer($data['question'], $data['locale'], $data['history'] ?? [], $data['page'] ?? [], $mode);

        if ($mode === 'answer') {
            $this->store->recordQuestion($data['question'], $data['locale'], $result['answered']);
        }

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:600'],
            'locale' => ['required', 'in:nl,fr,en'],
            'history' => ['array', 'max:24'],
            'history.*.role' => ['required', 'in:user,bot'],
            'history.*.text' => ['required', 'string', 'max:3000'],
            'page.title' => ['nullable', 'string', 'max:200'],
            'page.range' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:answer,advice'],
        ]);
    }

    /**
     * Zoals ask(), maar als server-sent events: `delta` met elk nieuw stukje
     * tekst terwijl het model schrijft, en `done` met het volledige antwoord,
     * de bronnen en de volgende stap. De bezoeker ziet zo binnen een seconde
     * de eerste woorden in plaats van na het hele antwoord.
     */
    public function stream(Request $request): StreamedResponse
    {
        $data = $this->validateQuestion($request);

        return response()->stream(function () use ($data) {
            // Buffers van PHP zelf leegmaken, zodat elk stukje meteen vertrekt.
            // Niet in een test: daar vangt de testrespons de uitvoer zelf op.
            while (! app()->runningUnitTests() && ob_get_level() > 0) {
                ob_end_flush();
            }

            $emit = function (string $event, array $payload): void {
                echo "event: {$event}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                flush();
            };

            $mode = $data['mode'] ?? 'answer';
            $result = $this->assistant->answer(
                $data['question'],
                $data['locale'],
                $data['history'] ?? [],
                $data['page'] ?? [],
                $mode,
                fn (string $text) => $emit('delta', ['text' => $text]),
            );

            if ($mode === 'answer') {
                $this->store->recordQuestion($data['question'], $data['locale'], $result['answered']);
            }

            $emit('done', $result);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Vult een formulier vooraf in uit wat de klant al vertelde, zodat de
     * assistent die vragen overslaat.
     */
    public function extract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form' => ['required', 'in:'.implode(',', array_keys(config('leadbot.forms')))],
            'text' => ['required', 'string', 'max:3000'],
            'locale' => ['required', 'in:nl,fr,en'],
        ]);

        return response()->json(['values' => (object) $this->assistant->extract($data['form'], $data['text'], $data['locale'])]);
    }

    public function helpdesk(Request $request): JsonResponse
    {
        $locale = in_array($request->query('locale'), ['nl', 'fr', 'en'], true) ? $request->query('locale') : 'nl';

        return response()->json([
            'categories' => trans('leadbot.categories', [], $locale),
            'items' => $this->knowledge->faq($locale),
        ]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate(['helpful' => ['required', 'boolean']]);

        $this->store->recordFeedback((bool) $data['helpful']);

        return response()->json(['ok' => true]);
    }
}
