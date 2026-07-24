# Asset Upload Compression — Design

**Date:** 2026-05-05
**Status:** Approved for planning
**Author:** Arnaud + Claude (brainstorm)

## Problem

Klanten uploaden foto's via de Statamic Control Panel (`/cp`). De Forge-server staat ingesteld op PHP `Max file upload size = 2MB`, waardoor uploads van moderne camera-/iPhone-foto's (vaak 5–15 MB) hard falen. Dit leidt tot frictie: klanten willen gewoon uploaden zonder zelf bestanden te hoeven verkleinen.

Tegelijk willen we de R2-storage en de Forge-server compact houden. Originele bestanden van 10 MB op R2 zetten is overkill voor een corporate website waar Glide toch kleinere varianten rendert.

## Goal

Klanten kunnen vrij grote foto's uploaden via het Control Panel zonder size-validatiefouten. Op R2 belanden geoptimaliseerde versies met behoud van visuele kwaliteit, zodat storage compact blijft.

## Constraints & Context

- **Stack:** Statamic 6, Laravel 12, PHP 8.4, Forge hosting, Cloudflare R2 als asset disk (`r2`).
- **Glide:** al actief met GD driver (`config/statamic/assets.php`), gebruikt voor on-the-fly delivery-resizing in templates.
- **Asset container:** alleen `assets` (op de `r2` disk). `assets.yaml`: `disk: r2`.
- **Upload-flow:** uitsluitend via Control Panel (geen front-end uploads).
- **Queue:** sync (`.env.example` heeft `QUEUE_CONNECTION=sync`). Redis draait wel op de server voor cache.

## Out of Scope

- **Front-end uploads** (forms): geen uploadflow buiten de CP nu.
- **Cloudflare Polish / Cloudflare Images:** Glide handelt al delivery-optimalisatie af; de bottleneck zit aan upload/storage, niet aan delivery.
- **Originals-backup:** geen aparte `originals/` map. Origineel wordt vervangen.
- **Iteratieve compressie / harde size cap:** YAGNI voor v1. Single-pass op breedte + quality is voldoende voor 95% van de gevallen.
- **Queued processing via Redis:** uitgesteld naar v2 (zie sectie "Future Work").

## Design

### Overview

Twee lagen:

1. **Forge-instellingen verhogen** zodat grote uploads de server bereiken.
2. **Server-side compressie listener** die `Statamic\Events\AssetUploaded` afvangt en het bestand verkleint vóór het permanent op R2 staat.

### Layer 1 — Forge PHP settings

| Setting | Huidige waarde | Nieuwe waarde |
|---|---|---|
| Max file upload size | 2 MB | **25 MB** |
| Max execution time | 30 s | **120 s** |

25 MB dekt iPhone Pro RAW + alle gangbare camera-uploads. 120 s geeft ruimte voor GD/Imagick processing op trage CPU's bij grote bestanden. Geen wijziging aan OPcache of PHP-versie.

### Layer 2 — Compressie pipeline

#### Components

- **`app/Listeners/CompressUploadedAsset.php`** — luistert op `Statamic\Events\AssetUploaded`. Filtert op container `assets` en op image-mime-types. Roept `ImageCompressor` aan en schrijft het resultaat terug naar de asset.
- **`app/Services/ImageCompressor.php`** — geïsoleerde service. Input: bestandspad/blob + mime-type. Output: gecomprimeerde blob. Geen kennis van Statamic events — pure image transformatie. Unit-testbaar.
- **`config/image-compression.php`** — config-bestand met:
  - `max_width` = `2500`
  - `jpeg_quality` = `85`
  - `enabled` = `true` (kill-switch)
  - `containers` = `['assets']` (welke containers we compresseren)
- **`app/Providers/EventServiceProvider.php`** — registreert listener mapping `AssetUploaded => [CompressUploadedAsset::class]`.

#### Behandeling per mime-type

| Mime | Actie |
|---|---|
| `image/jpeg` | Resize naar max 2500px breedte (alleen indien breder). Pas EXIF-oriëntatie toe, strip overige EXIF. Re-encode JPEG quality 85. |
| `image/png` | Resize naar max 2500px breedte (alleen indien breder). Lossless re-encode. Behoud transparantie (RGBA). |
| `image/heic`, `image/heif` | Convert naar JPEG q85 + resize. **Vereist Imagick** (GD ondersteunt geen HEIC). Bestandsnaam-extensie wordt naar `.jpg` gewijzigd. |
| `image/gif` | Skip (animaties zouden breken via GD). |
| `image/svg+xml` | Skip (vector — geen pixel-resize zinvol). |
| `image/webp` | Skip in v1 (al efficient formaat; klanten zouden het zelden uploaden). |
| Andere (pdf, video, …) | Skip. |

#### Library keuze

**Intervention Image v3** met de **GD** driver (al actief op de server). Reden: GD is ingebakken, geen extra extension nodig, en de Intervention API is schoner dan ruwe GD-calls. Voor HEIC schakelt de service naar Imagick als die beschikbaar is; anders log warning en skip.

#### Sync vs queued

**v1 = synchroon.** De listener draait binnen de upload-request. Met `Max execution time = 120s` is dat ruim voldoende voor één foto van 25 MB. Eenvoudigste implementatie, geen extra infrastructuur.

#### Error handling

- **Compressie faalt** (corrupt bestand, OOM, onbekend formaat) → log `warning` met asset-path en exception, laat origineel ongewijzigd op R2 staan. Upload-flow voor de klant blijft succesvol.
- **Imagick niet beschikbaar bij HEIC upload** → log warning, behoud HEIC ongemoeid (browser kan het niet renderen, maar bestand staat tenminste op R2).
- **R2 schrijf-fout bij terugschrijven** → laat originele upload staan, log error.

Geen exceptions naar de klant — de upload moet altijd "succesvol" voelen.

#### Data flow

```
Klant upload foto via /cp
   ↓
Statamic schrijft origineel naar R2
   ↓
AssetUploaded event vuurt
   ↓
CompressUploadedAsset listener
   ↓ (filter: container=assets, mime is in scope)
ImageCompressor::compress($asset)
   ↓ (download bytes van R2 → Intervention → resize+encode)
Asset->putContents($compressedBytes) → schrijft terug naar R2 op zelfde path
   ↓
Listener klaar → upload-request returnt naar CP
```

### Testing

**Unit tests** (`tests/Unit/ImageCompressorTest.php`):

- JPEG > 2500px wordt geresized en gerecompressed → output kleiner dan input, dimensies <= 2500.
- JPEG < 2500px wordt alleen gerecompressed → dimensies behouden, filesize kleiner.
- PNG met transparantie behoudt alpha-channel.
- Corrupt bestand → exception, geen crash.
- GIF / SVG → returnt input ongewijzigd (skip).

**Feature test** (`tests/Feature/AssetUploadCompressionTest.php`):

- Fake R2 disk, fire `AssetUploaded` event met fixture-asset, assert dat de file op de disk verkleind is.
- Assert dat assets in een andere container niet aangeraakt worden.

Test fixtures: kleine JPEG, grote JPEG (>3000px), PNG met alpha, corrupt JPEG. In `tests/fixtures/images/`.

## Configuration changes

- **`composer.json`:** add `intervention/image: ^3.0`.
- **`config/image-compression.php`:** new file (zie boven).
- **`app/Providers/EventServiceProvider.php`:** register listener.
- **`.env.example`:** geen wijzigingen (config heeft sane defaults).
- **Forge dashboard:** PHP `Max file upload size` = 25 MB, `Max execution time` = 120 s. Geen code-change, manuele setting.

## Future Work (v2)

**Queued processing via Redis:**

- `CompressUploadedAsset` listener dispatcht `CompressAssetJob implements ShouldQueue`.
- `.env`: `QUEUE_CONNECTION=redis`.
- Forge daemon: `php artisan queue:work redis --queue=default --timeout=120 --tries=3`.
- PHP `Max execution time` kan terug naar 30 s (webrequest is licht).
- Failed jobs → `failed_jobs` tabel, origineel blijft staan als degradation.

**Wanneer v2 doen:** zodra batch-uploads (>5 foto's tegelijk) traag aanvoelen in de CP, of zodra het synchroon comprimeren CP-timeouts veroorzaakt.

## Open Questions

Geen — alles afgestemd in brainstorm.
