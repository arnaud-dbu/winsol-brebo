<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use League\Flysystem\FilesystemException;
use Statamic\Facades\AssetContainer;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $config = config('inspace.assets');

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $config['mimes']),
                'max:'.$config['max_kb'],
                $this->usableFilename(...),
            ],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $container = AssetContainer::findByHandle($config['container']);

        // Symfony's UploadedFile haalt directorysegmenten al uit de
        // clientnaam (File::getName()), dus `../` of `sub/` bereikt hier
        // nooit meer het pad. basename() blijft staan als goedkope extra
        // zekerheid, niet als enige verdediging.
        $filename = basename($validated['file']->getClientOriginalName());

        $asset = $container->makeAsset(
            trim($config['folder'], '/').'/'.$filename
        );

        try {
            // upload() en geen eigen disk-write: alleen dit pad vuurt
            // AssetUploaded, waar CompressUploadedAsset aan hangt. Statamic's
            // eigen Uploader lost een naamconflict bovendien zelf op
            // (tijdstempel achter de bestandsnaam), dus deze upload
            // overschrijft nooit stil een bestaand bestand.
            $asset->upload($validated['file']);
        } catch (DecoderException|FilesystemException $e) {
            // Statamic genereert synchroon dimensies/preview uit de bytes
            // (Imaging\ImageGenerator::generateByAsset(), aangeroepen vanuit
            // save()), ná het wegschrijven van het bestand maar vóór er een
            // id wordt teruggegeven. deleteQuietly() ruimt het weesbestand en
            // de Stache-registratie op zonder AssetDeleting/AssetDeleted te
            // vuren voor een asset dat nooit compleet heeft bestaan.
            $asset->deleteQuietly();

            throw ValidationException::withMessages([
                'file' => 'Het bestand kon niet als afbeelding gelezen worden.',
            ]);
        }

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

    /**
     * Weigert een clientnaam die na basename() leeg is of op `.`/`..`
     * uitkomt. Zo'n naam levert geen pad "in" `folder` op maar de map zelf
     * (`folder/.`) of de bovenliggende map (`folder/..`), en Statamic's
     * eigen Uploader schrijft daar dan naar toe alsof het een bestand is.
     */
    private function usableFilename(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if (in_array(basename($value->getClientOriginalName()), ['', '.', '..'], true)) {
            $fail('De bestandsnaam is onbruikbaar.');
        }
    }
}
