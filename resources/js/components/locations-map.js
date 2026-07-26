/**
 * Leaflet komt binnen via een dynamische import achter een
 * IntersectionObserver, zoals sliders.js dat met Swiper doet: pagina's
 * zonder kaart betalen niets, en pagina's met kaart betalen pas bij het
 * scrollen. Vite splitst leaflet + leaflet.css in een eigen chunk.
 *
 * De coordinaten worden van de kaartjes zelf gelezen. Een aparte
 * data-locations='[...]'-blob op de container zou een tweede bron van
 * waarheid zijn die uit sync kan lopen met de gerenderde lijst.
 */

const SECTION_SELECTOR = '[data-section="locations"]'
const MAP_SELECTOR = '[data-locations-map]'
const CARD_SELECTOR = '[data-location-lat][data-location-lng]'
const PIN_SELECTOR = '[data-map-pin]'

const FOCUS_ZOOM = 13
const BOUNDS_PADDING = [40, 40]

const TILE_URL = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png'
const TILE_SUBDOMAINS = 'abcd'
const TILE_MAX_ZOOM = 20

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches

const canHover = () =>
    window.matchMedia('(hover: hover) and (pointer: fine)').matches

/**
 * Een kaartje zonder (geldige) coordinaten levert geen pin op maar houdt wel
 * zijn plek in de lijst — het blijft een werkende link naar /contact.
 */
function readLocations(section) {
    return Array.from(section.querySelectorAll(CARD_SELECTOR))
        .map((card) => ({
            card,
            lat: parseFloat(card.dataset.locationLat),
            lng: parseFloat(card.dataset.locationLng),
        }))
        .filter(({ lat, lng }) => Number.isFinite(lat) && Number.isFinite(lng))
}

async function createMap(section, container, locations) {
    // Leaflet 1.x is UMD, 2.x is ESM-only. Deze interop werkt voor allebei.
    const leaflet = await import('leaflet')
    const L = leaflet.default ?? leaflet
    await import('leaflet/dist/leaflet.css')

    const template = section.querySelector(PIN_SELECTOR)

    const icon = L.divIcon({
        html: template ? template.innerHTML : '',
        // Overschrijft leaflet-div-icon; de reset staat in locations.css.
        className: 'locations-map__pin',
        iconSize: [25, 33],
        iconAnchor: [12.5, 33],
    })

    // Alle interactie uit: het design toont geen zoomknoppen en de kaart is
    // illustratie. Dat voorkomt ook dat de pagina niet meer scrollt zodra de
    // muis boven de kaart hangt. De attributie wordt in de partial gerenderd,
    // buiten de aria-hidden container, dus Leaflet's eigen control blijft uit.
    const map = L.map(container, {
        attributionControl: false,
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false,
    })

    L.tileLayer(TILE_URL, {
        subdomains: TILE_SUBDOMAINS,
        maxZoom: TILE_MAX_ZOOM,
    }).addTo(map)

    locations.forEach(({ lat, lng }) => {
        L.marker([lat, lng], { icon, keyboard: false, interactive: false }).addTo(map)
    })

    // fitBounds over de echte pins, niet het hardcoded centrum uit Figma: zo
    // blijft het beeld kloppen als er een vestiging bijkomt of verhuist.
    const bounds = L.latLngBounds(locations.map(({ lat, lng }) => [lat, lng]))
    map.fitBounds(bounds, { padding: BOUNDS_PADDING })

    if (!canHover()) return map

    const reset = () => {
        if (prefersReducedMotion()) {
            map.fitBounds(bounds, { padding: BOUNDS_PADDING })
        } else {
            map.flyToBounds(bounds, { padding: BOUNDS_PADDING })
        }
    }

    locations.forEach(({ card, lat, lng }) => {
        const focus = () => {
            if (prefersReducedMotion()) {
                map.setView([lat, lng], FOCUS_ZOOM)
            } else {
                map.flyTo([lat, lng], FOCUS_ZOOM)
            }
        }

        // focusin/focusout naast de muis-events, zodat tab-navigatie hetzelfde
        // doet als hoveren.
        card.addEventListener('mouseenter', focus)
        card.addEventListener('focusin', focus)
        card.addEventListener('mouseleave', reset)
        card.addEventListener('focusout', reset)
    })

    return map
}

function register(section) {
    const container = section.querySelector(MAP_SELECTOR)
    if (!container) return

    const locations = readLocations(section)
    if (locations.length === 0) return

    const observer = new IntersectionObserver(
        (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return

            observer.disconnect()
            createMap(section, container, locations)
        },
        { rootMargin: '200px' }
    )

    observer.observe(container)
}

document.querySelectorAll(SECTION_SELECTOR).forEach(register)
