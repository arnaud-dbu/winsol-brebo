<?php

namespace App\Http\Controllers\Leadbot;

use App\Http\Controllers\Controller;
use App\Services\FormTimer;
use App\Services\Leadbot\Assistant;
use App\Services\Leadbot\DemoStore;
use App\Services\Leadbot\Knowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'question' => ['required', 'string', 'max:600'],
            'locale' => ['required', 'in:nl,fr,en'],
            'history' => ['array', 'max:24'],
            'history.*.role' => ['required', 'in:user,bot'],
            'history.*.text' => ['required', 'string', 'max:3000'],
            'page.title' => ['nullable', 'string', 'max:200'],
            'page.range' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:answer,advice'],
        ]);

        $mode = $data['mode'] ?? 'answer';
        $result = $this->assistant->answer($data['question'], $data['locale'], $data['history'] ?? [], $data['page'] ?? [], $mode);

        if ($mode === 'answer') {
            $this->store->recordQuestion($data['question'], $data['locale'], $result['answered']);
        }

        return response()->json($result);
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
