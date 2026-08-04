<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use App\Inspace\EntryLister;
use App\Inspace\EntryMapper;
use App\Inspace\SiteGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
