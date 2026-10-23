/*
 * Suggesties via de Places Autocomplete (New) REST-endpoint in plaats van de
 * Maps-JS-loader: dat scheelt het hele maps-bundle en houdt de dropdown
 * volledig in eigen markup. Het sessiontoken bundelt de keystrokes van één
 * adres én de Place Details-oproep na de keuze tot één factureerbare sessie,
 * en wordt daarna vernieuwd.
 */

const PLACES = 'https://places.googleapis.com/v1/places';

/**
 * Vak op het formulier per type uit `addressComponents`. Het eerste type dat
 * in de plaats voorkomt, wint.
 */
const COMPONENTS = {
    street: ['route'],
    number: ['street_number'],
    postal_code: ['postal_code'],
    city: ['locality', 'postal_town'],
};

/**
 * `crypto.randomUUID()` bestaat alleen in een secure context. Productie draait
 * op https, maar lokaal (http://…test) is hij undefined en gooide het hele
 * component een TypeError bij de eerste toetsaanslag — het adresveld deed dan
 * niets. `getRandomValues` is er wél buiten een secure context; die bouwt
 * hieronder dezelfde v4-vorm die Google voor een sessiontoken verwacht.
 */
function sessionToken() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));

    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function addressAutocomplete(key) {
    return {
        suggestions: [],
        open: false,
        active: -1,
        token: null,
        request: 0,

        async search() {
            const input = this.$refs.input.value.trim();

            if (!key || input.length < 3) {
                this.close();
                return;
            }

            this.token ??= sessionToken();
            const request = ++this.request;

            let data;
            try {
                const response = await fetch(`${PLACES}:autocomplete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Goog-Api-Key': key,
                    },
                    body: JSON.stringify({
                        input,
                        sessionToken: this.token,
                        languageCode: 'nl',
                        includedRegionCodes: ['be'],
                    }),
                });

                if (!response.ok) {
                    this.close();
                    return;
                }

                data = await response.json();
            } catch {
                this.close();
                return;
            }

            // Een oudere respons die een recentere inhaalt zou de lijst
            // terugdraaien naar verouderde suggesties.
            if (request !== this.request) {
                return;
            }

            this.suggestions = (data.suggestions ?? [])
                .map(({ placePrediction }) => ({
                    id: placePrediction?.placeId,
                    text: placePrediction?.text?.text,
                    street: placePrediction?.structuredFormat?.mainText?.text,
                }))
                .filter((suggestion) => suggestion.id && suggestion.text);
            this.active = -1;
            this.open = this.suggestions.length > 0;
        },

        move(step) {
            if (!this.open) {
                return;
            }

            const count = this.suggestions.length;
            this.active = (this.active + step + count) % count;
        },

        chooseActive(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                this.choose(this.active);
            }
        },

        async choose(index) {
            const suggestion = this.suggestions[index];
            const token = this.token;

            this.token = null;
            this.close();

            const parts = await this.fetchAddressParts(suggestion.id, token);

            if (!parts) {
                this.$refs.input.value = suggestion.street ?? suggestion.text;
                return;
            }

            const form = this.$refs.input.form;

            for (const [name, value] of Object.entries(parts)) {
                const input = form.elements.namedItem(name);

                // Zonder straat in de plaats (een gemeente gekozen) blijft wat
                // de klant typte staan. Een ontbrekend nummer maakt nr. wél
                // leeg: een oud nummer bij een nieuwe straat klopt niet.
                if (input && (value || name === 'number')) {
                    input.value = value ?? '';
                }
            }

            if (!parts.number) {
                form.elements.namedItem('number')?.focus();
            }
        },

        /**
         * Bij een fout `null`: dan krijgt het straatvak de tekst van de
         * suggestie, en de klant vult de rest met de hand.
         */
        async fetchAddressParts(id, token) {
            try {
                const params = new URLSearchParams({ sessionToken: token, languageCode: 'nl' });
                const response = await fetch(`${PLACES}/${encodeURIComponent(id)}?${params}`, {
                    headers: {
                        'X-Goog-Api-Key': key,
                        'X-Goog-FieldMask': 'addressComponents',
                    },
                });

                if (!response.ok) {
                    return null;
                }

                const { addressComponents = [] } = await response.json();

                return Object.fromEntries(
                    Object.entries(COMPONENTS).map(([name, types]) => [
                        name,
                        types
                            .map((type) => addressComponents.find((component) => component.types?.includes(type)))
                            .find(Boolean)?.longText,
                    ]),
                );
            } catch {
                return null;
            }
        },

        close() {
            this.open = false;
            this.active = -1;
            this.suggestions = [];
        },
    };
}
