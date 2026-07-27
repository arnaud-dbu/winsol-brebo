const BREAKPOINTS = {
    xs: 448,
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    '2xl': 1536,
};

/**
 * "1.15,md:2,xl:3" -> { slidesPerView: 1.15, breakpoints: { 768: {...}, 1280: {...} } }
 */
function parsePerView(value) {
    const config = { slidesPerView: 1, breakpoints: {} };

    for (const part of (value ?? '1').split(',')) {
        const [key, raw] = part.includes(':') ? part.split(':') : [null, part];
        const slidesPerView = parseFloat(raw);

        if (Number.isNaN(slidesPerView)) continue;

        if (key === null) {
            config.slidesPerView = slidesPerView;
        } else if (BREAKPOINTS[key]) {
            config.breakpoints[BREAKPOINTS[key]] = { slidesPerView };
        }
    }

    return config;
}

async function createSwiper(element) {
    const { default: Swiper } = await import('swiper');
    await import('swiper/css');
    const { Navigation, Pagination } = await import('swiper/modules');

    const { slidesPerView, breakpoints } = parsePerView(element.dataset.sliderPerView);

    return new Swiper(element, {
        modules: [Navigation, Pagination],
        slidesPerView,
        breakpoints,
        spaceBetween: 16,
        watchOverflow: true,
        a11y: { enabled: true },
        pagination: element.dataset.sliderPagination
            ? { el: element.querySelector('.swiper-pagination'), clickable: true }
            : false,
        navigation: element.dataset.sliderNavigation
            ? {
                  nextEl: element.querySelector('.swiper-button-next'),
                  prevEl: element.querySelector('.swiper-button-prev'),
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
