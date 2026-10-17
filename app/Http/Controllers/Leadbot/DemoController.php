<?php

namespace App\Http\Controllers\Leadbot;

use App\Http\Controllers\Controller;
use App\Services\Leadbot\Assistant;
use App\Services\Leadbot\DemoStore;
use App\Services\Leadbot\FaqWriter;
use App\Services\Leadbot\Knowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het nagebootste ERP-scherm voor de demo. Toont wat de echte ERP zou
 * ontvangen, in de vorm van de requestpipeline uit de funcspec.
 */
class DemoController extends Controller
{
    public function __construct(private DemoStore $store, private Knowledge $knowledge) {}

    public function show(Request $request, Assistant $assistant): View
    {
        return view('leadbot.demo', [
            'demo_key' => (string) $request->query('key', ''),
            'uses_model' => $assistant->usesModel(),
        ]);
    }

    public function state(): JsonResponse
    {
        return response()->json([
            ...$this->store->state(),
            'showrooms' => collect($this->knowledge->showrooms())->pluck('name', 'slug'),
            'statuses' => DemoStore::STATUSES,
            'categories' => trans('leadbot.categories', [], 'nl'),
            'faq_count' => count($this->knowledge->faq('nl')),
            'document_count' => count($this->knowledge->documents()),
        ]);
    }

    public function reset(): JsonResponse
    {
        $this->store->reset();

        return response()->json(['ok' => true]);
    }

    /**
     * Een open vraag beantwoorden zet het antwoord als gepubliceerd
     * FAQ-item in Statamic. Vanaf de volgende vraag kent de assistent het,
     * in elke taal, en Winsol kan het in de CP verder bijschaven.
     */
    public function answer(Request $request, string $id, FaqWriter $writer): JsonResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'max:1500'],
            'category' => ['nullable', 'in:'.implode(',', FaqWriter::CATEGORIES)],
        ]);

        $question = $this->store->markAnswered($id, $data['answer']);
        abort_if($question === null, 404);

        $writer->create($question['question'], $data['answer'], $data['category'] ?? 'producten', 'assistent');

        return response()->json(['ok' => true]);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string']]);

        abort_unless($this->store->setStatus($id, $data['status']), 422);

        return response()->json(['ok' => true]);
    }
}
