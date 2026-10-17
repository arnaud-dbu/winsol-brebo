/**
 * Het nagebootste ERP-scherm voor de demo van de website-assistent. Leest
 * elke drie seconden de toestand; wat sinds het openen binnenkwam, licht op.
 */
const FORM_LABELS = {
    offerte: 'Offerte',
    herstelling: 'Herstelling',
    brochure: 'Brochure',
    contact: 'Vraag',
    info: 'Vraag',
};

export function leadbotDemo() {
    return {
        key: '',
        endpoint: '',
        tab: 'requests',
        online: true,
        state: {
            requests: [],
            customers: [],
            questions: [],
            statuses: {},
            showrooms: {},
            stats: { questions: 0, answered: 0, leads: 0 },
        },
        known: null,
        fresh: [],
        selected: null,
        drafts: {},
        categories: {},
        toast: '',
        counts: { newRequests: 0, newRepairs: 0, openQuestions: 0 },

        init() {
            this.key = this.$el.dataset.key;
            this.endpoint = this.$el.dataset.state;
            this.refresh();
            setInterval(() => this.refresh(), 3000);
        },

        url(path) {
            const url = new URL(path, location.origin);
            if (this.key) url.searchParams.set('key', this.key);
            return url.toString();
        },

        async refresh() {
            try {
                const response = await fetch(this.url(this.endpoint), { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const state = await response.json();
                this.online = true;
                this.detectNew(state);
                this.state = state;
                this.counts = {
                    newRequests: state.requests.filter(
                        (request) => request.type !== 'herstelling' && request.status === 'nieuw',
                    ).length,
                    newRepairs: state.requests.filter(
                        (request) => request.type === 'herstelling' && request.status === 'nieuw',
                    ).length,
                    openQuestions: state.questions.filter((question) => !question.answer).length,
                };

                if (this.selected) {
                    this.selected = state.requests.find((request) => request.id === this.selected.id) ?? null;
                }
            } catch {
                this.online = false;
            }
        },

        detectNew(state) {
            const ids = [
                ...state.requests.map((request) => request.id),
                ...state.questions.map((question) => question.id),
            ];

            if (this.known === null || state.reset_at !== this.state.reset_at) {
                this.known = new Set(ids);
                return;
            }

            const added = ids.filter((id) => !this.known.has(id));
            added.forEach((id) => this.known.add(id));

            if (!added.length) return;

            this.fresh.push(...added);
            setTimeout(() => (this.fresh = this.fresh.filter((id) => !added.includes(id))), 6000);

            const request = state.requests.find((candidate) => added.includes(candidate.id));
            const question = state.questions.find((candidate) => added.includes(candidate.id));

            if (request) {
                this.tab = request.type === 'herstelling' ? 'repairs' : 'requests';
                this.notify(
                    `Nieuwe ${request.type === 'herstelling' ? 'reparatie' : 'aanvraag'} ${request.number} via de website-assistent`,
                );
            } else if (question) {
                this.notify('Nieuwe vraag die de assistent niet kon beantwoorden');
            }
        },

        notify(text) {
            this.toast = text;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = ''), 5000);
        },

        requestsIn(status) {
            return this.state.requests
                .filter((request) => request.type !== 'herstelling' && request.status === status)
                .sort((a, b) => b.created_at.localeCompare(a.created_at));
        },

        repairs() {
            return this.state.requests
                .filter((request) => request.type === 'herstelling')
                .sort((a, b) => b.created_at.localeCompare(a.created_at));
        },

        questionsSorted() {
            return [...this.state.questions].sort(
                (a, b) => !!a.answer - !!b.answer || b.asked_at.localeCompare(a.asked_at),
            );
        },

        customer(request) {
            return (
                this.state.customers.find((customer) => customer.id === request.customer_id) ?? {
                    name: '?',
                    history: [],
                }
            );
        },

        showroomName(slug) {
            return (this.state.showrooms[slug] ?? slug ?? '').replace('Winsol ', '');
        },

        detailRows(request) {
            return Object.entries(request.fields ?? {}).filter(
                ([label]) => !['Naam', 'E-mail', 'Telefoon'].includes(label),
            );
        },

        formLabel(form) {
            return FORM_LABELS[form] ?? 'Aanvraag';
        },

        hasFiles(request) {
            return Object.keys(request.fields ?? {}).some((label) => /foto|factuur|bijlage/i.test(label));
        },

        feedbackRate() {
            const { helpful = 0, unhelpful = 0 } = this.state.stats;
            const total = helpful + unhelpful;
            return total ? `${Math.round((helpful / total) * 100)}% van de antwoorden nuttig (${total} stemmen)` : '';
        },

        answeredRate() {
            const { questions, answered } = this.state.stats;
            return questions ? `${Math.round((answered / questions) * 100)}%` : '-';
        },

        age(iso) {
            const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (minutes < 1) return 'zojuist';
            if (minutes < 60) return `${minutes} min`;
            const hours = Math.round(minutes / 60);
            if (hours < 24) return `${hours} u`;
            return `${Math.round(hours / 24)} d`;
        },

        select(request) {
            this.selected = request;
        },

        async post(path, body = {}) {
            const response = await fetch(this.url(`${this.endpoint.replace(/\/state$/, '')}/${path}`), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            await this.refresh();
            return response.ok;
        },

        async setStatus(request, status) {
            await this.post(`requests/${request.id}/status`, { status });
        },

        async answer(question) {
            const text = (this.drafts[question.id] || '').trim();
            if (!text) return;
            if (
                await this.post(`questions/${question.id}`, {
                    answer: text,
                    category: this.categories[question.id] || 'producten',
                })
            ) {
                this.notify('Toegevoegd aan de kennis van de assistent');
            }
        },

        async reset() {
            if (!confirm('Alle demo-aanvragen en vragen terugzetten naar de startstand?')) return;
            this.known = null;
            this.selected = null;
            await this.post('reset');
        },
    };
}
