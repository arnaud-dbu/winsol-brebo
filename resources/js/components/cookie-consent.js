/**
 * Cookie consent — Alpine component.
 *
 * Gates third-party scripts behind user consent and (optionally) updates
 * Google Consent Mode v2 signals after a choice is made.
 *
 * Script-gating contract for project developers:
 *   <script type="text/plain" data-cookie-category="marketing"> ... </script>
 * Scripts with `type="text/plain"` are inert by default; this component
 * replaces them with executable <script> elements once the matching
 * category is granted.
 */

const COOKIE_NAME = 'cookie_consent';
const COOKIE_MAX_AGE_DAYS = 180;
const OPTIONAL_CATEGORIES = ['marketing', 'personalization', 'analytics'];

const CONSENT_MODE_MAP = {
    marketing: ['ad_storage', 'ad_user_data', 'ad_personalization'],
    personalization: ['personalization_storage'],
    analytics: ['analytics_storage'],
};

function readCookie() {
    const raw = document.cookie.split('; ').find((row) => row.startsWith(`${COOKIE_NAME}=`));
    if (!raw) return null;
    try {
        return JSON.parse(decodeURIComponent(raw.slice(COOKIE_NAME.length + 1)));
    } catch {
        return null;
    }
}

function writeCookie(payload) {
    const value = encodeURIComponent(JSON.stringify(payload));
    const maxAge = COOKIE_MAX_AGE_DAYS * 24 * 60 * 60;
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE_NAME}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax${secure}`;
}

function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
}

export function cookieConsent(config = {}) {
    return {
        version: String(config.version ?? '1'),
        consentModeV2: Boolean(config.consentModeV2),

        visible: false,
        expanded: false,
        // Default the toggles to on so an undecided visitor who opens the
        // preferences panel starts from "all allowed" and switches off what they
        // don't want. A returning visitor's saved choice overrides these in
        // init(); nothing is actually granted until they confirm.
        choices: {
            marketing: true,
            personalization: true,
            analytics: true,
        },

        init() {
            const stored = readCookie();
            if (stored && String(stored.version) === this.version) {
                OPTIONAL_CATEGORIES.forEach((c) => {
                    this.choices[c] = Boolean(stored[c]);
                });
                this.applyConsent({ updateConsentMode: this.consentModeV2 });
            } else {
                this.visible = true;
            }

            window.addEventListener('cookie-consent:open', () => this.open());
            window.openCookiePreferences = () => this.open();
        },

        open() {
            this.visible = true;
            this.expanded = true;
            this.$nextTick(() => this.focusPanel());
        },

        toggleExpanded() {
            this.expanded = !this.expanded;
            if (this.expanded) {
                this.$nextTick(() => this.focusPanel());
            }
        },

        focusPanel() {
            const panel = this.$refs.panel;
            if (!panel) return;
            const scroller = panel.querySelector('[data-scroll]');
            if (scroller) scroller.scrollTop = 0;
            panel.focus({ preventScroll: true });
        },

        toggle(category) {
            this.choices[category] = !this.choices[category];
        },

        accept() {
            OPTIONAL_CATEGORIES.forEach((c) => (this.choices[c] = true));
            this.persistAndClose();
        },

        deny() {
            OPTIONAL_CATEGORIES.forEach((c) => (this.choices[c] = false));
            this.persistAndClose();
        },

        confirm() {
            this.persistAndClose();
        },

        persistAndClose() {
            writeCookie({
                version: this.version,
                timestamp: Date.now(),
                ...this.choices,
            });
            this.applyConsent({ updateConsentMode: this.consentModeV2 });
            // Only hide the banner. Do NOT collapse the panel here: toggling
            // `expanded` in the same tick fires the child x-collapse leave at the
            // same time as this aside's x-show/x-transition leave, and the two
            // transitions race so the aside never receives `display: none`. The
            // panel is inside the hidden aside anyway; `open()` re-expands it.
            this.visible = false;
        },

        applyConsent({ updateConsentMode }) {
            OPTIONAL_CATEGORIES.forEach((category) => {
                if (!this.choices[category]) return;
                document
                    .querySelectorAll(`script[type="text/plain"][data-cookie-category="${category}"]`)
                    .forEach((node) => activateScript(node));
            });

            if (updateConsentMode) {
                const update = {};
                OPTIONAL_CATEGORIES.forEach((category) => {
                    const value = this.choices[category] ? 'granted' : 'denied';
                    CONSENT_MODE_MAP[category].forEach((signal) => {
                        update[signal] = value;
                    });
                });
                gtag('consent', 'update', update);
            }
        },
    };
}

function activateScript(node) {
    const replacement = document.createElement('script');
    for (const attr of node.attributes) {
        if (attr.name === 'type') continue;
        replacement.setAttribute(attr.name, attr.value);
    }
    if (node.src) {
        replacement.src = node.src;
    } else {
        replacement.text = node.textContent;
    }
    node.parentNode.replaceChild(replacement, node);
}
