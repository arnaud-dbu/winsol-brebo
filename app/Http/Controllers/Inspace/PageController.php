<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use App\Inspace\EntryLister;
use App\Inspace\EntryMapper;
use App\Inspace\EntryWriter;
use App\Inspace\ExternalImageException;
use App\Inspace\PayloadValidator;
use App\Inspace\SiteGuard;
use App\Inspace\UnknownBlockException;
use App\Inspace\UnresolvableAssetException;
use App\Inspace\WriteLockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Entry;

class PageController extends Controller
{
    public function index(Request $request, EntryLister $lister): JsonResponse
    {
        // `site` en `collection` zijn de enige queryparameters die ongefilterd
        // in een typed `?string`-parameter belanden (`SiteGuard::resolve()`,
        // `EntryLister::handles()`); de rest gaat via `boolean()`/casting en
        // is daarmee al veilig voor een array-waarde. Zonder deze guard geeft
        // `?collection[]=a` een kale 500 TypeError in plaats van een 422.
        Validator::make($request->query(), [
            'site' => ['sometimes', 'nullable', 'string'],
            'collection' => ['sometimes', 'nullable', 'string'],
        ])->validate();

        app(SiteGuard::class)->resolve($request->query('site'));

        return response()->json($lister->list([
            'collection' => $request->query('collection'),
            'editable' => $request->has('editable') ? $request->boolean('editable') : null,
            'status' => $request->query('status'),
            'page' => $request->query('page'),
            'per_page' => $request->query('per_page'),
        ]));
    }

    public function show(string $id, EntryMapper $mapper): JsonResponse
    {
        $entry = Entry::find($id);

        if ($entry === null) {
            return response()->json(['message' => 'Onbekende entry.'], 404);
        }

        return response()->json($mapper->toApi($entry));
    }

    public function store(Request $request, PayloadValidator $validator, EntryWriter $writer, EntryMapper $mapper): JsonResponse
    {
        $collection = 'articles';

        if (! $mapper->isWritable($collection)) {
            return response()->json(['message' => 'Deze collectie is niet schrijfbaar.'], 403);
        }

        $validator->validate($collection, $request->all(), creating: true);

        app(SiteGuard::class)->resolve($request->input('site'));

        try {
            $result = $writer->create($collection, $request->all());
        } catch (ExternalImageException|UnknownBlockException $e) {
            throw ValidationException::withMessages(['content' => $e->getMessage()]);
        } catch (UnresolvableAssetException $e) {
            throw ValidationException::withMessages([$e->apiName => $e->getMessage()]);
        } catch (WriteLockTimeoutException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $this->logWrite($request, $result['entry']->id());

        return response()->json(
            $mapper->toApi($result['entry']) + ['warnings' => $result['warnings']],
            $result['existing'] ? 200 : 201
        );
    }

    public function update(
        string $id,
        Request $request,
        PayloadValidator $validator,
        EntryWriter $writer,
        EntryMapper $mapper
    ): JsonResponse {
        $entry = Entry::find($id);

        if ($entry === null) {
            return response()->json(['message' => 'Onbekende entry.'], 404);
        }

        if (! $mapper->isWritable($entry->collectionHandle())) {
            return response()->json([
                'message' => 'Deze collectie is niet schrijfbaar.',
                'writable_collections' => array_keys(config('inspace.writable', [])),
            ], 403);
        }

        $validator->validate($entry->collectionHandle(), $request->all(), creating: false);

        try {
            $result = $writer->update($entry, $request->all());
        } catch (ExternalImageException|UnknownBlockException $e) {
            throw ValidationException::withMessages(['content' => $e->getMessage()]);
        } catch (UnresolvableAssetException $e) {
            throw ValidationException::withMessages([$e->apiName => $e->getMessage()]);
        } catch (WriteLockTimeoutException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $this->logWrite($request, $result['entry']->id());

        return response()->json($mapper->toApi($result['entry']) + ['warnings' => $result['warnings']]);
    }

    private function logWrite(Request $request, string $entryId): void
    {
        Log::info('inspace.write', [
            'token' => $request->attributes->get('inspace_token_label'),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'entry' => $entryId,
        ]);
    }
}
