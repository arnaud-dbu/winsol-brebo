import Alpine from 'alpinejs'
import Collapse from '@alpinejs/collapse'
import { cookieConsent } from './components/cookie-consent'
import { articleFilter } from './components/article-filter'

window.Alpine = Alpine
Alpine.plugin(Collapse)
Alpine.data('cookieConsent', cookieConsent)
Alpine.data('articleFilter', articleFilter)
Alpine.start()

import "./components/hamburger";
import "./components/mobile-navigation";
import "./components/sliders";
import "./components/locations-map";

if (import.meta.hot) {
    import.meta.hot.on("vite:beforeFullReload", () => {
        sessionStorage.setItem(
            "__vite_scroll",
            JSON.stringify({ x: window.scrollX, y: window.scrollY }),
        );
    });

    const saved = sessionStorage.getItem("__vite_scroll");
    if (saved) {
        const { x, y } = JSON.parse(saved);
        sessionStorage.removeItem("__vite_scroll");
        requestAnimationFrame(() => window.scrollTo(x, y));
    }
}
