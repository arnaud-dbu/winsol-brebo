/*
 * Suggesties via de Places Autocomplete (New) REST-endpoint in plaats van de
 * Maps-JS-loader: dat scheelt het hele maps-bundle en houdt de dropdown
 * volledig in eigen markup. Het sessiontoken bundelt de keystrokes van één
 * adres tot één factureerbare sessie en wordt na een keuze vernieuwd.
 */
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

            this.token ??= crypto.randomUUID();
            const request = ++this.request;

            let data;
            try {
                const response = await fetch('https://places.googleapis.com/v1/places:autocomplete', {
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
                .map((suggestion) => suggestion.placePrediction?.text?.text)
                .filter(Boolean);
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

        choose(index) {
            this.$refs.input.value = this.suggestions[index];
            this.token = null;
            this.close();
        },

        close() {
            this.open = false;
            this.active = -1;
            this.suggestions = [];
        },
    };
}
