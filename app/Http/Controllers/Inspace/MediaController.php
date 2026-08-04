<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\AssetContainer;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $config = config('inspace.assets');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $config['mimes']), 'max:'.$config['max_kb']],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $container = AssetContainer::findByHandle($config['container']);

        // basename() sluit uit dat een clientnaam met `/` of `../` het pad in
        // gaat: ongefilterd zou dat wegschrijven buiten `folder` toelaten, of
        // erger, Asset::path() gooit een ongevangen PathTraversalDetected op
        // een `..`-segment (500 in plaats van een nette 422).
        $filename = basename($validated['file']->getClientOriginalName());

        $asset = $container->makeAsset(
            trim($config['folder'], '/').'/'.$filename
        );

        // upload() en geen eigen disk-write: alleen dit pad vuurt
        // AssetUploaded, waar CompressUploadedAsset aan hangt. Statamic's
        // eigen Uploader lost een naamconflict bovendien zelf op (tijdstempel
        // achter de bestandsnaam), dus deze upload overschrijft nooit stil
        // een bestaand bestand.
        $asset->upload($validated['file']);

        if (($validated['alt'] ?? null) !== null) {
            $asset->set('alt', $validated['alt'])->save();
        }

        return response()->json([
            'id' => $asset->id(),
            'url' => $asset->url(),
            'width' => $asset->width(),
            'height' => $asset->height(),
            'filename' => $asset->basename(),
            'alt' => $asset->get('alt'),
        ], 201);
    }
}
