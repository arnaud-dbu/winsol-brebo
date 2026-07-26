/**
 * Filtert de projectgrid op /realisaties.
 *
 * De server rendert altijd álle projecten en zet `hidden` op de kaarten die
 * bij de eerste paint niet matchen. Dit component neemt datzelfde attribuut
 * over via `:hidden`, zodat server en client hetzelfde mechanisme gebruiken:
 * geen flits bij het booten, geen animatie, en "Toon alles" werkt zonder
 * request omdat alle kaarten al in de DOM staan.
 *
 * Leest `?range=` zelf uit `window.location.search` in plaats van een
 * server-geïnterpoleerd argument aan te nemen: een ruwe `{{ get:range }}`
 * in `x-data="projectFilter('...')"` zou de queryparameter ongefilterd in
 * een Alpine-expressie plaatsen, wat een reflected-XSS-gat opent (de browser
 * decodeert HTML-entities vóórdat Alpine de attribuutwaarde evalueert, dus
 * escapen aan de serverkant is hier geen verdediging). Door geen argument
 * aan te nemen verdwijnt het injectiepunt volledig; de server-side
 * `{{ get:range }}`-vergelijkingen in de template en in `rangeFilter`
 * blijven wel bestaan, want die belanden in een HTML-attribuutwaarde/klasse-
 * vergelijking, niet in JS-code.
 */
export function projectFilter() {
    const initial = new URLSearchParams(window.location.search).get('range')

    return {
        active: initial || 'all',

        matches(slug) {
            return this.active === 'all' || this.active === slug
        },

        select(slug) {
            this.active = slug || 'all'

            const url = new URL(window.location)

            if (this.active === 'all') {
                url.searchParams.delete('range')
            } else {
                url.searchParams.set('range', this.active)
            }

            window.history.replaceState({}, '', url)
        },
    }
}
