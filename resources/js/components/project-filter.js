/**
 * Filtert de projectgrid op /realisaties.
 *
 * De server rendert altijd álle projecten en zet `hidden` op de kaarten die
 * bij de eerste paint niet matchen. Dit component neemt datzelfde attribuut
 * over via `:hidden`, zodat server en client hetzelfde mechanisme gebruiken:
 * geen flits bij het booten, geen animatie, en "Toon alles" werkt zonder
 * request omdat alle kaarten al in de DOM staan.
 */
export function projectFilter(initial = '') {
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
