/**
 * Filtert de artikelgrid op /nieuws.
 *
 * De server rendert altijd álle artikels en zet `hidden` op de kaarten die
 * bij de eerste paint niet matchen. Dit component neemt datzelfde attribuut
 * over via `:hidden`, zodat server en client hetzelfde mechanisme gebruiken:
 * geen flits bij het booten, geen animatie, en "Toon alles" werkt zonder
 * request omdat alle kaarten al in de DOM staan.
 *
 * Leest `?theme=` zelf uit `window.location.search` in plaats van een
 * server-geïnterpoleerd argument aan te nemen: een ruwe `{{ get:theme }}`
 * in `x-data="articleFilter('...')"` zou de queryparameter ongefilterd in
 * een Alpine-expressie plaatsen, wat een reflected-XSS-gat opent (de browser
 * decodeert HTML-entities vóórdat Alpine de attribuutwaarde evalueert, dus
 * escapen aan de serverkant is hier geen verdediging). Door geen argument
 * aan te nemen verdwijnt het injectiepunt volledig; de server-side
 * `{{ get:theme }}`-vergelijkingen in de template en in `themeFilter`
 * blijven wel bestaan, want die belanden in een HTML-attribuutwaarde of een
 * klassevergelijking, niet in JS-code.
 */
export function articleFilter(param = 'theme') {
    // `param` is een letterlijke naam uit de template ('theme', 'groep'),
    // nooit bezoekersinvoer — de XSS-afweging hierboven blijft dus gelden.
    const initial = new URLSearchParams(window.location.search).get(param);

    return {
        active: initial || 'all',

        matches(slug) {
            return this.active === 'all' || this.active === slug;
        },

        select(slug) {
            this.active = slug || 'all';

            const url = new URL(window.location);

            if (this.active === 'all') {
                url.searchParams.delete(param);
            } else {
                url.searchParams.set(param, this.active);
            }

            window.history.replaceState({}, '', url);
        },
    };
}
