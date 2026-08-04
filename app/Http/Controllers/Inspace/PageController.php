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
use App\Inspace\WriteLockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Entry;

class PageController extends Controller
{
    public function index(Request $request, EntryLister $lister): JsonResponse
    {
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

        try {
            $result = $writer->create($collection, $request->all());
        } catch (ExternalImageException|UnknownBlockException $e) {
            throw ValidationException::withMessages(['content' => $e->getMessage()]);
        } catch (WriteLockTimeoutException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $this->logWrite($request, $result['entry']->id());

        return response()->json(
            $mapper->toApi($result['entry']) + ['warnings' => $result['warnings']],
            $result['existing'] ? 200 : 201
        );
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
