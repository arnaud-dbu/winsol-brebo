const BREAKPOINTS = {
    xs: 448,
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    '2xl': 1536,
};

/**
 * "1.15,md:2,xl:3" -> { 0: 1.15, 768: 2, 1280: 3 }
 *
 * Sleutel 0 is de basiswaarde (het deel zonder `breakpoint:`-prefix); zonder
 * zo'n deel valt hij terug op `fallback`.
 */
function parseTiers(value, fallback) {
    const tiers = { 0: fallback };

    for (const part of (value ?? '').split(',')) {
        const [key, raw] = part.includes(':') ? part.split(':') : [null, part];
        const parsed = parseFloat(raw);

        if (Number.isNaN(parsed)) continue;

        if (key === null) {
            tiers[0] = parsed;
        } else if (BREAKPOINTS[key]) {
            tiers[BREAKPOINTS[key]] = parsed;
        }
    }

    return tiers;
}

/**
 * Swiper-breakpoints zijn niet cumulatief: hij kiest de hoogste matchende
 * breakpoint en merget uitsluitend díe over de basisparameters — lagere
 * breakpoints worden overgeslagen. `per_view="1.15,md:2"` met
 * `space="16,lg:32"` zou op `lg` dus terugvallen op de basis-slidesPerView
 * van 1.15 in plaats van de 2 die `md` zette.
 *
 * Daarom stapelt deze functie beide assen zelf: elke breakpoint die in één
 * van de twee attributen voorkomt, krijgt een volledig paar mee met de
 * laatst bekende waarde van de andere as.
 */
function buildResponsive(element) {
    const perView = parseTiers(element.dataset.sliderPerView, 1);
    const space = parseTiers(element.dataset.sliderSpace, 16);

    const widths = [...new Set([...Object.keys(perView), ...Object.keys(space)])]
        .map(Number)
        .sort((a, b) => a - b);

    const breakpoints = {};
    let slidesPerView = perView[0];
    let spaceBetween = space[0];

    for (const width of widths) {
        slidesPerView = perView[width] ?? slidesPerView;
        spaceBetween = space[width] ?? spaceBetween;

        if (width > 0) breakpoints[width] = { slidesPerView, spaceBetween };
    }

    return { slidesPerView: perView[0], spaceBetween: space[0], breakpoints };
}

/**
 * Houdt `--slider-gap` gelijk aan de tier van `space` die bij de huidige
 * viewport hoort.
 *
 * Swiper leest `space` als `spaceBetween`, maar boven `data-slider-from`
 * bestaat er geen Swiper meer en neemt het CSS-grid het over — zie de
 * `--slider-gap`-regels in resources/css/components/slider.css. Zonder deze
 * spiegeling zou elke tier op of boven het `from`-breakpoint niets doen.
 *
 * Sliders zonder `space` krijgen niets inline en houden dus de terugval uit
 * de stylesheet.
 */
function syncGap(element) {
    if (!element.dataset.sliderSpace) return;

    const tiers = parseTiers(element.dataset.sliderSpace, 16);
    const queries = Object.keys(tiers)
        .map(Number)
        .filter(Boolean)
        .sort((a, b) => a - b)
        .map((width) => [width, window.matchMedia(`(min-width: ${width}px)`)]);

    const apply = () => {
        const [width] = queries.filter(([, query]) => query.matches).pop() ?? [0];
        element.style.setProperty('--slider-gap', `${tiers[width]}px`);
    };

    queries.forEach(([, query]) => query.addEventListener('change', apply));
    apply();
}

async function createSwiper(element) {
    const { default: Swiper } = await import('swiper');
    await import('swiper/css');
    const { Navigation, Pagination } = await import('swiper/modules');

    /*
     * De nav staat naast `[data-slider]` in plaats van erin, zodat hij in de
     * secties die hem bij de kop zetten een eigen grid-item kan zijn — zie
     * `.slider-shell` in resources/views/partials/slider.antlers.html en de
     * `lg`-regels in resources/css/components/slider.css. De paginering blijft
     * wel binnen de slider zelf.
     */
    const shell = element.closest('.slider-shell') ?? element;

    return new Swiper(element, {
        modules: [Navigation, Pagination],
        ...buildResponsive(element),
        watchOverflow: true,
        a11y: { enabled: true },
        pagination: element.dataset.sliderPagination
            ? { el: element.querySelector('.swiper-pagination'), clickable: true }
            : false,
        navigation: element.dataset.sliderNavigation
            ? {
                  nextEl: shell.querySelector('.swiper-button-next'),
                  prevEl: shell.querySelector('.swiper-button-prev'),
              }
            : false,
    });
}

/**
 * Zonder data-slider-from draait de slider altijd. Met data-slider-from="md"
 * draait hij alleen ONDER die breakpoint; erboven wordt hij vernietigd zodat
 * de CSS-grid het overneemt.
 *
 * enable()/disable() zijn geserialiseerd via `queue`: elke aanroep wordt
 * aan de vorige gekoppeld en wacht tot die volledig is afgerond (inclusief
 * de async createSwiper()) voordat hij zelf start. Zo kan snel heen-en-weer
 * schakelen over de breakpoint nooit twee instanties opleveren, en kan een
 * disable() nooit "verdwijnen" doordat instance nog null is terwijl een
 * enable() nog in-flight is — de laatste aanroep in de rij bepaalt altijd
 * de uiteindelijke staat.
 */
function register(element) {
    syncGap(element);

    let instance = null;
    let queue = Promise.resolve();
    const from = element.dataset.sliderFrom;

    const enable = () => {
        queue = queue.then(async () => {
            if (!instance) instance = await createSwiper(element);
        });
        return queue;
    };

    const disable = () => {
        queue = queue.then(() => {
            if (instance) {
                instance.destroy(true, true);
                instance = null;
            }
        });
        return queue;
    };

    if (!from) {
        enable();
        return;
    }

    const query = window.matchMedia(`(min-width: ${BREAKPOINTS[from]}px)`);
    const sync = () => (query.matches ? disable() : enable());

    query.addEventListener('change', sync);
    sync();
}

document.querySelectorAll('[data-slider]').forEach(register);
