#!/usr/bin/env bash
#
# De toets van ticket 05: antwoordt `robots.txt` met een 200, en noemt hij een
# sitemap die zelf ook bestaat?
#
#   ./robots.sh                             tegen de productiesite
#   ./robots.sh http://winsol-brebo.test    tegen een andere basis, bijvoorbeeld Herd
#
# De inhoud staat als bestand in `public/robots.txt`, de statuscode komt uit
# nginx. Op een server met `public/` als documentroot serveert nginx dat bestand
# rechtstreeks en geeft het een 200. Lokaal in Herd niet: daar staat de root op
# `/`, dus nginx vindt het bestand niet, komt op zijn eigen 404 uit, en stuurt
# het verzoek via `error_page` alsnog door. De inhoud is dan goed en de code
# niet. Wil je lokaal ook een 200, haal dan de regel
# `location = /robots.txt` uit `herd.conf`.
#
# `RobotsTest` toetst de inhoud van het bestand en kan de statuscode niet zien;
# die ontstaat pas in nginx. Vandaar dit script.
#
# Twee verzoeken per run, dus vrij te draaien vanaf elke machine.

set -u

case "${1:-}" in
    -*) echo "Onbekende optie: $1. Geef een basis-URL of niets." >&2; exit 2 ;;
esac

if [ "$#" -gt 1 ]; then
    echo "Eén basis tegelijk; $2 valt af." >&2
    exit 2
fi

BASIS="${1:-https://winsol-brebo.be}"
BASIS="${BASIS%/}"

mislukt=0
fout() {
    printf '  FOUT  %s\n' "$1"
    mislukt=$((mislukt + 1))
}

koppen="$(mktemp)"
inhoud="$(mktemp)"
trap 'rm -f "$koppen" "$inhoud"' EXIT

echo "robots.txt op ${BASIS}"
echo

if ! curl --silent --show-error --max-time 20 --dump-header "$koppen" --output "$inhoud" "${BASIS}/robots.txt"; then
    fout "geen antwoord van ${BASIS}/robots.txt"
else
    status="$(grep -Eo '^HTTP/[0-9.]+ [0-9]{3}' "$koppen" | grep -Eo '[0-9]{3}$' | head -1)"
    content_type="$(grep -i '^content-type:' "$koppen" | tail -1 | sed 's/^[^:]*: *//' | tr -d '\r')"

    # Bewust géén `--location`: een omleiding is hier ook een fout. Google leest
    # alles behalve een 200 als "er is geen robots.txt".
    if [ "$status" = "200" ]; then
        printf '  ok    statuscode 200\n'
    else
        fout "statuscode ${status} in plaats van 200 (nginx, niet de applicatie: zie de kop van dit script)"
    fi

    case "$content_type" in
        text/plain*) printf '  ok    %s\n' "$content_type" ;;
        *) fout "content-type is ${content_type:-leeg} en niet text/plain" ;;
    esac

    # De richtlijnen zelf zijn niet gevoelig voor hoofdletters of extra witruimte,
    # dus de toets hier ook niet.
    if grep -Eiq '^user-agent:[[:space:]]*\*' "$inhoud"; then
        printf '  ok    User-agent-regel aanwezig\n'
    else
        fout "geen 'User-agent: *' in de inhoud"
    fi

    # Alleen een `/` op zichzelf sluit de site af. Zonder het anker aan het eind
    # zou elke gewone regel als `Disallow: /cp` hier ten onrechte SITE_INDEXABLE
    # de schuld geven.
    if grep -Eiq '^disallow:[[:space:]]*/[[:space:]]*$' "$inhoud"; then
        # Een afgesloten site hoort geen `Sitemap:`-regel te hebben, dus die
        # blijft hier ongetoetst: anders staan er twee fouten voor één oorzaak.
        fout "de site sluit zichzelf af met 'Disallow: /', dus SITE_INDEXABLE staat op false; zie ticket 02"
    else
        sitemap="$(grep -i '^sitemap:' "$inhoud" | head -1 | sed 's/^[^:]*: *//' | tr -d '\r')"
        if [ -z "$sitemap" ]; then
            fout "geen 'Sitemap:'-regel in de inhoud"
        else
            printf '  ok    Sitemap: %s\n' "$sitemap"
            # De regel is er om gevolgd te worden. Wijst hij naar een adres dat
            # zelf niet bestaat, dan is de 200 hierboven niets waard.
            sitemap_status="$(curl --silent --output /dev/null --max-time 20 \
                --write-out '%{http_code}' "$sitemap")"
            if [ "$sitemap_status" = "200" ]; then
                printf '  ok    die sitemap antwoordt met 200\n'
            else
                fout "de genoemde sitemap antwoordt met ${sitemap_status}"
            fi
        fi
    fi
fi

echo
if [ "$mislukt" -eq 0 ]; then
    echo "Alles goed."
else
    echo "${mislukt} keer mis."
fi

exit $((mislukt > 0))
