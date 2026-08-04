# Inspace adapter, Winsol Brebo

Statamic does not ship a write API: its built-in REST and GraphQL endpoints
are read-only. This adapter was built specifically to give Nova a write path.
`openapi.yaml` in this folder is the contract, and this file explains the
parts that a spec alone cannot.

## What phase 1 covers

Writable is one collection: `articles`, the blog. You can create an article
and update it in full, including uploading its image.

Readable is everything on the site, not just the blog: offering pages,
product pages, ranges, locations, quicklinks and legal pages all come back
through `GET /pages` alongside articles, so Nova can look up titles and URLs
to build internal links. Those other collections are not writable yet.

That is a deliberate phasing. Offering and product pages are built from
loose content blocks that differ per site, and what Nova should be able to do
with them (rewrite text only, or also add and reorder sections) decides what
that contract looks like. That question is still open.

## Getting started

1. `GET /schema`: which fields are writable, which are required, and which
   theme values exist. That list is site-specific and can change.
2. `POST /media`: upload your image, keep the returned `id`.
3. `POST /pages`: the article itself. Put that `id` (or the `url` from the
   same response — both work) straight into `image`; do the same for
   `meta_image` if you set one. A reference that does not resolve to an
   uploaded asset gives a `422` on that field instead of publishing without
   a hero image.

**What "an asset reference" means.** `image` and `meta_image` accept two
shapes as input: the `id` and the `url` that `POST /media` returns for the
same upload. `GET /pages/{id}` echoes a third, internal shape back in those
same fields — it is not one of the two input shapes, but it is safe to send
straight back. This matters because the normal way to change one field on an
article is to `GET` it, edit what you want to change, and `PATCH` the whole
thing back — and that means `image`/`meta_image` usually travel through this
API in their `GET` shape, not the `POST /media` shape. All three shapes
round-trip: read an article, send it back completely unchanged (even a field
you have no intention of touching), and it keeps pointing at the same asset.
A reference that does not resolve to any uploaded asset — in any of the
three shapes — gives a `422` on that field instead of silently publishing
without an image.

The `required` and `max` values in `GET /schema` are guaranteed to match what
`POST /pages` and `PATCH /pages/{id}` actually enforce: both read from the
same configuration, and a test in our suite fails the build if they ever
drift apart. You can rely on `/schema` as the live source of truth instead of
re-checking `ArticleWrite` in `openapi.yaml` by hand.

The smallest possible call:

```json
{
  "title": "Choosing zip screens for a new build",
  "theme": "zonwering",
  "image": "3f2a...",
  "content": [{ "type": "text", "html": "<h2>Keeping the outside out</h2><p>...</p>" }],
  "status": "draft",
  "external_id": "nova-4711"
}
```

## Three things that differ from a typical blog API

**`content` is a list, not a field.** For an ordinary article that list is
one element long, and all your HTML sits in `html`: headings, bold text,
lists, links, tables and inline images. The list exists because an article
can also contain blocks that are not HTML, for example a video. Those come
back as a closed box with only a `type` and an `id`. Send them back
unchanged; reordering is fine, leaving one out means deleting it. You can
never create one from scratch: the `id` only exists because we matched it
against a block that was already stored, so a brand new article can only
contain `text` blocks. Send an opaque block on `POST /pages` and you get a
`422`, not a working video.

**Alt text belongs to the file, not the placement.** Statamic stores alt
text on the asset, not on where it is used. Set it on `POST /media`. An
`alt` attribute on an `<img>` inside `content` is ignored, and that gets
reported in `warnings`.

**Images must already be uploaded.** An `<img src>` pointing at another
domain gives a `422`. Upload it first through `/media` and reference it by
the asset id it returns (or the URL that came back from a previous `GET`,
Statamic accepts both).

## What you get back

Every successful write can carry a `warnings` array alongside the object
itself: HTML tags that were stripped, alt attributes that were ignored. Those
are not errors, but they are the only signal that something did not come
through as intended. Log them.

## Errors you can expect

| Status | When |
| --- | --- |
| 401 | Missing or invalid bearer token. |
| 403 | The collection you are writing to is not `articles` (only returned by `PATCH`, since `POST /pages` always targets `articles`). |
| 404 | `GET`/`PATCH /pages/{id}` with an id that does not exist. |
| 422 | A validation problem: unknown field, unknown theme, disallowed content, an image that was never uploaded, or an opaque block with an unknown or missing id. The response body always has an `errors` object keyed by field. |
| 429 | Rate limit exceeded. The default is 120 requests per minute per token. |
| 503 | The write could not go through for a reason that has nothing to do with your payload (see below). Retry unchanged. |

The `503` covers two situations on every write route (`POST /pages`,
`PATCH /pages/{id}`, `POST /media`):

- another write to the same collection is holding the internal write lock
  longer than we wait for it;
- the `articles` collection has revisions turned on in the CMS. With
  revisions on, saving would create a draft working copy instead of
  publishing, and Nova would think it published something that never went
  live. We would rather refuse the call than let that happen silently.

Both are operational conditions on our side, not something a different
payload fixes.

### A rare one we want to be upfront about

If a stored article ever has a corrupted content block (missing the
internal id it needs to be found again on save), a write to that article
returns a plain `500`, not a `422`. We do this on purpose: that is a defect
in how the content was stored, not something your request caused or can fix
by changing it. The same defect also surfaces as a plain `500` on a `GET
/pages/{id}` of that article, since reading it back has to look up that same
id. If you see a `500` on `GET`/`PATCH /pages/{id}`, it is on us to
investigate; retrying with the same payload will not help, but please send
us the entry id and timestamp so we can look at the stored content.

## Multi-site

This site currently has one site, `nl`. `GET /pages` and `POST /pages`
accept a `site` parameter and reject an unknown value with a `422`; you can
otherwise leave it out. `PATCH /pages/{id}` accepts the same field without
error but ignores it, since the entry you are updating already has a site.

## Test environment

The staging URL in `openapi.yaml` is disposable and not indexed. You can
create real articles there. CMS access is provided separately, so you can
see where your content lands.
