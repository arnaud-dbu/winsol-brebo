#!/usr/bin/env bash
#
# De steekproef van ticket 04: per laag van de redirecttabel één adres, en per
# adres de vraag of het in één sprong op een 200 landt zonder onderweg naar
# `http` te zakken.
#
#   ./steekproef.sh                 de volle steekproef over het echte DNS
#   ./steekproef.sh --preflight     dezelfde steekproef, maar met het oude
#                                   domein hard naar de nieuwe server gewezen
#   ./steekproef.sh --bestemmingen  alleen de bestemmingskant, op de nieuwe site
#
# De steekproef en de preflight lopen over apex én `www`: de geïndexeerde links
# staan grotendeels op `www`.
#
# Draai de volle steekproef NIET vanaf de ontwikkelmachine: de oude site bant
# een IP 24 uur na ongeveer 200 verzoeken. Gebruik een ander netwerk, een
# telefoon met wifi uit, of een server. De steekproef zelf kost een stuk of
# veertig verzoeken: twee hosts maal acht adressen maal de sprong erachteraan.

set -u

OUDE_HOSTS=(winsoldilbeek.be www.winsoldilbeek.be)
NIEUWE_SITE="https://winsol-brebo.be"
NIEUWE_SERVER="129.212.214.7"

# De adressen staan in `steekproef.txt`, want `LegacyRedirectTest` leest
# dezelfde lijst en houdt hem zo gelijk met de tabel.
HIER="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CASES=()
while IFS= read -r regel; do
    case "$regel" in
        \#*|"") ;;
        *) CASES+=("$regel") ;;
    esac
done < "${HIER}/steekproef.txt"

if [ "${#CASES[@]}" -eq 0 ]; then
    echo "Geen adressen in ${HIER}/steekproef.txt." >&2
    exit 2
fi

modus="steekproef"
case "${1:-}" in
    --preflight) modus="preflight" ;;
    --bestemmingen) modus="bestemmingen" ;;
    "") ;;
    *) echo "Onbekende optie: $1" >&2; exit 2 ;;
esac

if [ "$#" -gt 1 ]; then
    echo "Eén stand tegelijk; $2 valt af." >&2
    exit 2
fi

# Op de oude host hoort er precies één sprong te staan, op de nieuwe site geen.
# Zonder dat verschil telt een bestemming die zélf nog omleidt als goed, en dat
# is juist de keten die dit ticket moet uitsluiten.
if [ "$modus" = "bestemmingen" ]; then
    SPRONGEN=0
else
    SPRONGEN=1
fi

mislukt=0

# De koppen van de hele keten, met het eindadres eronder. `toets()` haalt daar
# de statuscodes, de `Location`-koppen en het eindadres uit.
keten() {
    local url="$1"
    shift
    curl --silent --show-error --head --location --max-time 20 \
        --write-out '\nEINDADRES %{url_effective}\n' "$@" "$url" 2>&1
}

toets() {
    local label="$1" url="$2" verwacht_adres="$3" verwachte_status="$4"
    shift 4

    local uitvoer statussen locaties eindadres eerste eindstatus sprongen redenen

    uitvoer="$(keten "$url" "$@")" || true

    statussen="$(printf '%s\n' "$uitvoer" | grep -Eo '^HTTP/[0-9.]+ [0-9]{3}' | grep -Eo '[0-9]{3}$')"
    locaties="$(printf '%s\n' "$uitvoer" | grep -i '^location:' | sed 's/^[Ll]ocation: *//' | tr -d '\r')"
    eindadres="$(printf '%s\n' "$uitvoer" | grep '^EINDADRES ' | tail -1 | cut -d' ' -f2-)"

    eerste="$(printf '%s\n' "$statussen" | head -1)"
    eindstatus="$(printf '%s\n' "$statussen" | tail -1)"
    sprongen="$(printf '%s\n' "$locaties" | grep -c .)"
    redenen=""

    if [ -z "$eerste" ]; then
        redenen="geen antwoord: $(printf '%s' "$uitvoer" | head -1)"
    else
        [ "$eerste" = "301" ] || [ "$SPRONGEN" -eq 0 ] || redenen="${redenen}eerste antwoord is $eerste en geen 301; "
        [ "$sprongen" -eq "$SPRONGEN" ] || redenen="${redenen}${sprongen} sprongen in plaats van ${SPRONGEN}; "
        [ "$eindstatus" = "$verwachte_status" ] || redenen="${redenen}eindigt op $eindstatus in plaats van ${verwachte_status}; "
        [ "$eindadres" = "$verwacht_adres" ] || redenen="${redenen}komt uit op ${eindadres}; "
        printf '%s\n' "$locaties" | grep -qi '^http://' && redenen="${redenen}zakt onderweg naar http; "
    fi

    if [ -z "$redenen" ]; then
        printf '  ok    %-18s %s\n' "$label" "$url"
    else
        printf '  FOUT  %-18s %s\n        %s\n' "$label" "$url" "${redenen%; }"
        mislukt=$((mislukt + 1))
    fi
}

case "$modus" in
    steekproef)
        for host in "${OUDE_HOSTS[@]}"; do
            echo "Steekproef over https://${host}, volgt het echte DNS."
            echo
            for regel in "${CASES[@]}"; do
                IFS='|' read -r label pad adres status <<< "$regel"
                toets "$label" "https://${host}${pad}" "$adres" "$status"
            done
            echo
        done
        ;;
    preflight)
        # Het oude domein hard naar de nieuwe server gewezen, zodat de tabel te
        # toetsen is vóór er bij de registrar iets omgaat. Werkt pas zodra het
        # domein in Forge op de site staat: daarvoor weigert nginx de naam.
        # `--insecure` staat er omdat het certificaat pas ná de DNS-omzetting
        # aangevraagd kan worden; deze modus zegt dus niets over het certificaat.
        echo "Preflight: de oude hosts → ${NIEUWE_SERVER}, buiten het DNS om."
        echo "Het certificaat blijft hier ongetoetst."
        echo
        for host in "${OUDE_HOSTS[@]}"; do
            echo "  https://${host}"
            for regel in "${CASES[@]}"; do
                IFS='|' read -r label pad adres status <<< "$regel"
                # Ook poort 80 omleggen: zakt een keten daarheen, dan zou curl
                # alsnog het echte DNS volgen en de oude server bevragen, en
                # díé bant een IP na ongeveer 200 verzoeken.
                toets "$label" "https://${host}${pad}" "$adres" "$status" \
                    --insecure \
                    --resolve "${host}:443:${NIEUWE_SERVER}" \
                    --resolve "${host}:80:${NIEUWE_SERVER}"
            done
            echo
        done
        ;;
    bestemmingen)
        echo "Alleen de bestemmingskant, op ${NIEUWE_SITE}."
        echo
        for regel in "${CASES[@]}"; do
            IFS='|' read -r label pad adres status <<< "$regel"
            toets "$label" "$adres" "$adres" "$status"
        done
        ;;
esac

echo
if [ "$mislukt" -eq 0 ]; then
    echo "Alles goed."
else
    echo "${mislukt} keer mis, op ${#CASES[@]} adressen per host."
fi

exit $((mislukt > 0))
