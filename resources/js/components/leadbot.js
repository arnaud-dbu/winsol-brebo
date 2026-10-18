/**
 * De website-assistent. Drie dingen in één paneel:
 *
 * - vrije vragen, beantwoord door de server uit de inhoud van de site;
 * - een helpdesk met de veelgestelde vragen, doorzoekbaar;
 * - de vier formulieren van de site als gesprek. Welke velden, keuzes en
 *   verplichtingen er zijn, komt uit de blueprint (zie App\Services\Leadbot\
 *   FormSchema). Aan het eind post de assistent naar hetzelfde endpoint als
 *   het formulier op de pagina, met dezelfde spamfilters en dezelfde mails.
 *
 * Het gesprek blijft bewaard terwijl de bezoeker door de site klikt
 * (sessionStorage); alleen gekozen bestanden overleven een paginawissel niet.
 */

const STORAGE_KEY = 'leadbot_state_v2';
const TRACKING_KEYS = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term'];

function storage(action, key, value) {
    try {
        if (action === 'get') return JSON.parse(sessionStorage.getItem(key));
        if (action === 'remove') return sessionStorage.removeItem(key);
        sessionStorage.setItem(key, JSON.stringify(value));
    } catch {
        return null;
    }
}

function normalize(text) {
    return (text ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

function isPhone(value) {
    const digits = value.replace(/[\s./()-]/g, '');
    return /^(?:(?:\+|00)[1-9]\d{7,13}|0[1-9]\d{7,9})$/.test(digits);
}

export function leadbot() {
    return {
        cfg: { strings: {}, ranges: [], products: [], showrooms: [], forms: {}, endpoints: {} },
        t: {},
        open: false,
        teaser: false,
        unread: false,
        view: 'home',
        messages: [],
        draft: '',
        inputMode: 'question',
        busy: false,
        submitting: false,
        flow: null,
        files: {},
        expecting: null,
        pendingFile: null,
        session: null,
        nudging: false,
        nudges: 0,
        page: { range: null, product: null },
        status: { text: '', tone: 'closed' },
        helpdesk: { loaded: false, items: [], categories: {}, query: '', category: '', openId: null, voted: {} },
        counter: 0,

        init() {
            this.cfg = JSON.parse(document.getElementById('leadbot-config').textContent);
            this.t = this.cfg.strings;
            this.page = this.detectPage();
            this.status = this.overallStatus();
            this.captureTracking();
            this.restore();

            window.addEventListener('pagehide', () => this.persist());
            document.addEventListener(
                'visibilitychange',
                () => document.visibilityState === 'hidden' && this.persist(),
            );

            if (!this.open && !storage('get', 'leadbot_teaser_dismissed')) {
                setTimeout(() => {
                    this.teaser = !this.open;
                    this.unread = !this.open;
                    this.nudge();
                }, 3500);
            }

            this.$watch('open', (open) => document.documentElement.classList.toggle('leadbot-locked', open));
        },

        tr(key, params = {}) {
            return Object.entries(params).reduce(
                (text, [name, value]) => text.replaceAll(`:${name}`, value ?? ''),
                this.t[key] ?? key,
            );
        },

        // Paneel en weergaven

        toggle() {
            this.open = !this.open;
            this.teaser = false;
            this.unread = false;

            if (this.open) {
                this.$refs.home?.scrollTo({ top: 0 });
                this.track('leadbot_open', { range: this.page.range?.handle ?? '' });
                this.$nextTick(() => this.focusInput());
            }

            this.persist();
        },

        /**
         * Winny vraagt de aandacht met een korte wiebel, opnieuw na een
         * pauze, maar hoogstens vier keer per pagina en nooit meer zodra de
         * bezoeker het paneel opent of de begroeting wegklikt.
         */
        nudge() {
            if (this.open || !this.teaser || this.nudges >= 4) return;

            this.nudges++;
            this.nudging = true;
            setTimeout(() => (this.nudging = false), 950);
            setTimeout(() => this.nudge(), 9000 + this.nudges * 3000);
        },

        dismissTeaser() {
            this.teaser = false;
            this.unread = false;
            storage('set', 'leadbot_teaser_dismissed', true);
        },

        show(view) {
            this.view = view;

            if (view === 'chat' && this.messages.length === 0) {
                this.greet();
            }

            if (view === 'helpdesk') {
                this.loadHelpdesk();
            }

            if (view === 'home') {
                this.$nextTick(() => this.$refs.home?.scrollTo({ top: 0 }));
            }

            this.$nextTick(() => {
                this.scrollDown(false);
                this.focusInput();
            });
            this.persist();
        },

        restart() {
            this.messages = [];
            this.flow = null;
            this.files = {};
            this.inputMode = 'question';
            this.draft = '';
            this.greet();
            this.persist();
        },

        greet() {
            const range = this.page.range;

            this.say({
                text: range ? this.tr('home_context', { range: range.title.toLowerCase() }) : this.t.home_title,
                chips: this.actionChips(range),
            });
        },

        actionChips(range = null) {
            return [
                {
                    label: range
                        ? this.tr('start_offerte_for', { range: range.title.toLowerCase() })
                        : this.t.start_offerte,
                    action: 'form',
                    value: 'offerte',
                    primary: true,
                },
                { label: this.t.start_herstelling, action: 'form', value: 'herstelling' },
                { label: this.t.start_brochure, action: 'form', value: 'brochure' },
                { label: this.t.show_showrooms, action: 'showrooms' },
            ];
        },

        startFromHome(form) {
            this.show('chat');
            this.startForm(form);
        },

        askFromHome() {
            const text = this.draft.trim();
            if (!text) return;
            this.draft = '';
            this.show('chat');
            this.ask(text);
        },

        // Berichten

        push(message) {
            this.messages.forEach((existing) => {
                if (existing.chips || existing.card === 'group') existing.locked = true;
            });

            const entry = { id: ++this.counter, from: 'bot', ...message };
            entry.chips = entry.chips?.map((chip, index) => ({ key: `${entry.id}-${index}`, ...chip }));
            this.messages.push(entry);

            if (!this.open && entry.from === 'bot') {
                this.unread = true;
            }

            this.$nextTick(() => this.scrollDown());
            this.persist();

            return this.messages[this.messages.length - 1];
        },

        say(message) {
            return this.push({ from: 'bot', ...message });
        },

        echo(text) {
            return this.push({ from: 'user', text });
        },

        scrollDown(smooth = true) {
            const stream = this.$refs.stream;
            if (stream) stream.scrollTo({ top: stream.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
        },

        focusInput() {
            if (!window.matchMedia('(pointer: fine)').matches) return;
            const target =
                this.view === 'helpdesk'
                    ? this.$refs.helpdeskSearch
                    : this.view === 'home'
                      ? this.$refs.homeInput
                      : this.$refs.input;
            target?.focus();
        },

        placeholder() {
            return (
                {
                    text: this.t.placeholder_text,
                    textarea: this.t.placeholder_textarea,
                    number: this.t.placeholder_number,
                }[this.inputMode] ?? this.t.placeholder
            );
        },

        // Invoer

        send() {
            const text = this.draft.trim();

            if (!text || this.busy) return;

            this.draft = '';
            this.$refs.input?.style.removeProperty('height');

            if (this.flow && this.expecting) {
                return this.answerText(text);
            }

            this.ask(text);
        },

        composerKey(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.send();
            }
        },

        grow(event) {
            const area = event.target;
            area.style.height = 'auto';
            area.style.height = `${Math.min(area.scrollHeight, 140)}px`;
        },

        choose(message, chip) {
            if (message.locked && chip.action !== 'feedback') return;

            switch (chip.action) {
                case 'toggle':
                    chip.selected = !chip.selected;
                    return;
                case 'confirm':
                    return this.confirmMulti(message);
                case 'answer':
                    this.echo(chip.label);
                    return this.store(chip.handle, chip.value);
                case 'skip':
                    this.echo(chip.label);
                    return this.store(chip.handle, null);
                case 'file':
                    this.pendingFile = chip.handle;
                    this.$refs.file.accept = chip.accept ?? 'image/*';
                    return this.$refs.file.click();
                case 'form':
                    this.echo(chip.label);
                    return this.startForm(chip.value, { story: chip.story });
                case 'showrooms':
                    this.echo(chip.label);
                    return this.say({ card: 'showrooms' });
                case 'ask':
                    return this.ask(chip.value);
                case 'advise':
                    return this.ask(this.t.help_choose_question);
            }
        },

        // Vrije vragen

        /**
         * Een vraag tussendoor breekt een lopend formulier niet af: na het
         * antwoord vraagt de assistent de stap waar hij was gewoon opnieuw.
         */
        async ask(question, { mode = 'answer', echo = true } = {}) {
            const resume = this.flow && !this.submitting;

            if (echo) this.echo(question);
            this.busy = true;
            const pending = this.say({ thinking: true });

            try {
                const result = await this.streamAnswer(pending, {
                    question,
                    locale: this.cfg.locale,
                    history: this.transcript().slice(-9, -1),
                    page: { title: document.title, range: this.page.range?.handle ?? null },
                    mode,
                });

                Object.assign(pending, {
                    thinking: false,
                    text: result.answer,
                    sources: result.sources,
                    question,
                    feedback: result.answered ? 'ask' : null,
                });
                this.track('leadbot_question', { answered: result.answered });

                if (resume && mode === 'advice') {
                    this.nextStep();
                } else if (resume) {
                    this.resumeStep();
                } else {
                    this.followUp(result, question);
                }
            } catch {
                Object.assign(pending, { thinking: false, text: this.t.error });
                this.say({ chips: [{ label: this.t.show_showrooms, action: 'showrooms' }] });
            } finally {
                this.busy = false;
                this.persist();
                this.$nextTick(() => this.focusInput());
            }
        },

        /**
         * Leest het antwoord als server-sent events: elk `delta` komt meteen
         * in de ballon, `done` brengt het definitieve antwoord en de bronnen.
         * Lukt streamen niet (oude browser, proxy die buffert), dan valt dit
         * terug op het gewone JSON-endpoint.
         */
        async streamAnswer(pending, payload) {
            const body = JSON.stringify(payload);
            const writer = this.typewriter(pending);

            try {
                const response = await fetch(this.cfg.endpoints.stream, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
                    body,
                });

                if (!response.ok || !response.body) throw new Error(response.status);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let result = null;

                while (!result) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    let boundary;

                    while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                        const block = buffer.slice(0, boundary);
                        buffer = buffer.slice(boundary + 2);
                        const event = block.match(/^event: (.+)$/m)?.[1];
                        const data = JSON.parse(block.match(/^data: (.+)$/m)?.[1] ?? 'null');

                        if (event === 'delta' && data) writer.add(data.text);
                        if (event === 'done') result = data;
                    }
                }

                if (!result) throw new Error('stream');

                await writer.finish(result.answer);
                return result;
            } catch {
                const response = await fetch(this.cfg.endpoints.ask, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body,
                });

                if (!response.ok) throw new Error(response.status);

                const result = await response.json();
                await writer.finish(result.answer, true);
                return result;
            }
        },

        /**
         * Laat het antwoord vloeiend verschijnen, zoals de grote chat-apps
         * het doen: in een vast tempo per teken (ongeveer 90 per seconde),
         * los van hoe schokkerig de stukjes van de server binnenkomen, en
         * telkens per heel woord, zodat er nooit een half woord staat. Loopt
         * er veel tekst achter, dan versnelt hij om bij te blijven. Elk nieuw
         * woord is een eigen span en fadet zelf in (zie .leadbot-word); is
         * het antwoord af, dan wordt het weer gewone tekst.
         */
        typewriter(message) {
            const CHARS_PER_SECOND = 90;
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let target = [];
            let raw = '';
            let running = false;
            let settle = null;
            let budget = 0;
            let last = 0;

            const tokenize = (text) => text.match(/\s*\S+\s*/g) ?? [];

            const tick = (now) => {
                const shown = message.tokens?.length ?? 0;

                if (shown >= target.length) {
                    running = false;
                    settle?.();
                    return;
                }

                const backlog = target.slice(shown).join('').length;
                const rate = CHARS_PER_SECOND + Math.max(0, backlog - 120) * 1.5;
                budget += reduced ? Infinity : ((now - (last || now)) / 1000) * rate;
                last = now;

                let next = shown;
                while (next < target.length && (budget >= target[next].length || (next === 0 && shown === 0))) {
                    budget -= target[next].length;
                    next++;
                }

                if (next > shown) {
                    const follow = this.nearBottom();
                    message.thinking = false;
                    message.typing = true;
                    message.tokens = target.slice(0, next);
                    message.text = message.tokens.join('');
                    if (follow) this.$nextTick(() => this.scrollDown(false));
                }

                requestAnimationFrame(tick);
            };

            const start = () => {
                if (!running) {
                    running = true;
                    last = 0;
                    budget = 0;
                    requestAnimationFrame(tick);
                }
            };

            return {
                add(text) {
                    raw += text;
                    // Het laatste woord kan nog half zijn; dat wacht op het volgende stukje.
                    target = tokenize(raw.replace(/\S+$/, ''));
                    start();
                },
                finish(final, fromScratch = false) {
                    // Het definitieve antwoord kan afwijken van wat al
                    // verscheen (bv. het vangnet voor prijzen): dan herbegint hij.
                    if (fromScratch || !final.startsWith(message.text ?? '')) {
                        message.tokens = [];
                        message.text = '';
                    }

                    raw = final;
                    target = tokenize(final);
                    start();

                    return new Promise((resolve) => {
                        settle = () => {
                            message.typing = false;
                            message.tokens = null;
                            resolve();
                        };
                        if (!running) settle();
                    });
                },
            };
        },

        /** Volgt de lezer het gesprek onderaan? Dan schuift het mee; anders blijft het staan. */
        nearBottom() {
            const stream = this.$refs.stream;
            return !stream || stream.scrollHeight - stream.scrollTop - stream.clientHeight < 96;
        },

        followUp(result, question) {
            if (!result.answered) {
                const message = this.messages[this.messages.length - 1];
                message.feedback = null;
                message.chips = [
                    {
                        key: `h-${message.id}`,
                        label: this.t.handoff_yes,
                        action: 'form',
                        value: 'contact',
                        story: question,
                        primary: true,
                    },
                    { key: `s-${message.id}`, label: this.t.show_showrooms, action: 'showrooms' },
                ];
                return;
            }

            const suggestions = (result.suggestions ?? []).map((text, index) => ({
                key: `q-${this.counter}-${index}`,
                label: text,
                action: 'ask',
                value: text,
                suggestion: true,
            }));

            const range = this.cfg.ranges.find((candidate) => candidate.handle === result.range) ?? this.page.range;
            const chip = {
                offerte: {
                    label: range
                        ? this.tr('start_offerte_for', { range: range.title.toLowerCase() })
                        : this.t.start_offerte,
                    value: 'offerte',
                },
                herstelling: { label: this.t.start_herstelling, value: 'herstelling' },
                brochure: { label: this.t.start_brochure, value: 'brochure' },
                contact: { label: this.t.start_contact, value: 'contact' },
            }[result.next_action];

            const message = this.messages[this.messages.length - 1];
            message.chips = [
                ...(chip
                    ? [
                          {
                              key: `f-${message.id}`,
                              action: 'form',
                              story: question,
                              primary: true,
                              range: range?.handle,
                              ...chip,
                          },
                      ]
                    : []),
                ...suggestions,
            ];

            if (result.next_action === 'showroom') {
                this.say({ card: 'showrooms' });
            }
        },

        async feedback(message, helpful) {
            message.feedback = helpful ? 'up' : 'down';
            this.persist();

            fetch(this.cfg.endpoints.feedback, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ helpful }),
            }).catch(() => {});

            if (!helpful) {
                this.say({
                    text: this.t.unknown_handoff,
                    chips: [
                        {
                            label: this.t.handoff_yes,
                            action: 'form',
                            value: 'contact',
                            story: message.question,
                            primary: true,
                        },
                        { label: this.t.show_showrooms, action: 'showrooms' },
                    ],
                });
            }
        },

        // Formulieren als gesprek

        async startForm(handle, { story = null } = {}) {
            const form = this.cfg.forms[handle];

            if (!form) return;

            this.flow = {
                form: handle,
                index: -1,
                values: this.contextValues(handle),
                asked: [],
                editing: false,
            };
            this.files = {};
            this.ensureSession();
            this.track('leadbot_form_start', { form: handle });

            this.say({ text: this.t[`intro_${handle}`] });

            if (story && handle === 'contact') {
                this.flow.values.message = story;
            } else if (story && this.cfg.usesModel) {
                await this.prefill(handle, story);
            }

            this.nextStep();
        },

        /** Wat de pagina al verraadt: de productgroep of het product waar de bezoeker op zit. */
        contextValues(handle) {
            const values = {};
            const { range, product } = this.page;

            if (handle === 'offerte' && range) values.products = [range.handle];
            if (handle === 'offerte' && product) values.product = product.id;
            if (handle === 'herstelling' && range) values.product = range.handle;

            return values;
        },

        async prefill(handle, story) {
            this.busy = true;
            const pending = this.say({ thinking: true });

            try {
                const response = await fetch(this.cfg.endpoints.extract, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ form: handle, text: story, locale: this.cfg.locale }),
                });
                const { values } = response.ok ? await response.json() : { values: {} };
                const fields = this.fields();
                const filled = Object.keys(values ?? {}).filter((key) => fields[key]);

                filled.forEach((key) => (this.flow.values[key] = values[key]));
                this.messages.splice(this.messages.indexOf(pending), 1);

                if (filled.length) {
                    this.say({
                        text: this.tr('prefilled', {
                            fields: filled.map((key) => fields[key].label.replace(/\?$/, '').toLowerCase()).join(', '),
                        }),
                    });
                }
            } catch {
                this.messages.splice(this.messages.indexOf(pending), 1);
            } finally {
                this.busy = false;
            }
        },

        stopFlow(announce = true) {
            this.flow = null;
            this.files = {};
            this.inputMode = 'question';
            this.messages.forEach((message) => (message.locked = true));

            if (announce) {
                this.say({ text: this.t.flow_stopped, chips: this.actionChips(this.page.range) });
            }
        },

        resumeStep() {
            const step = this.steps()[this.flow.index];

            if (step) {
                this.askStep(step.filter((field) => field.kind !== 'product'));
            } else {
                this.showSummary();
            }
        },

        steps() {
            return this.cfg.forms[this.flow.form].steps;
        },

        fields() {
            return Object.fromEntries(
                this.steps()
                    .flat()
                    .map((field) => [field.handle, field]),
            );
        },

        progress() {
            if (!this.flow) return null;
            const steps = this.steps().filter((step) => step.some((field) => field.kind !== 'product'));
            const current = Math.min(Math.max(this.flow.index + 1, 1), steps.length);

            return { current, total: steps.length, percent: Math.round((current / (steps.length + 1)) * 100) };
        },

        filled(field) {
            const value = this.flow.values[field.handle];
            if (field.kind === 'files') return this.flow.asked.includes(field.handle);
            if (Array.isArray(value)) return value.length > 0;
            return value !== undefined && value !== null && value !== '';
        },

        nextStep() {
            const steps = this.steps();

            while (++this.flow.index < steps.length) {
                const step = steps[this.flow.index].filter((field) => field.kind !== 'product');

                if (
                    step.length &&
                    !step.every(
                        (field) => this.filled(field) || (!field.required && this.flow.asked.includes(field.handle)),
                    )
                ) {
                    return this.askStep(step);
                }
            }

            this.showSummary();
        },

        askStep(step) {
            this.inputMode = 'none';
            const isGroup = step.length > 1 || step[0].kind === 'consent' || step[0].kind === 'address';

            if (isGroup) {
                return this.askGroup(step);
            }

            const field = step[0];
            const value = this.flow.values[field.handle];
            const intro =
                field.instructions && field.kind !== 'text' ? `${field.ask}\n${field.instructions}` : field.ask;

            switch (field.kind) {
                case 'choice': {
                    const options = this.sortOptions(field);
                    return this.say({
                        text: intro,
                        chips: options.map((option) => ({
                            label: option.label,
                            value: option.value,
                            handle: field.handle,
                            action: 'answer',
                            selected: option.value === value,
                        })),
                    });
                }
                case 'multi':
                    return this.say({
                        text: intro,
                        multi: true,
                        handle: field.handle,
                        chips: [
                            ...field.options.map((option) => ({
                                label: option.label,
                                value: option.value,
                                action: 'toggle',
                                selected: (value ?? []).includes(option.value),
                            })),
                            { label: this.t.next, action: 'confirm', primary: true },
                            ...(field.handle === 'products' ? [{ label: this.t.help_choose, action: 'advise' }] : []),
                        ],
                    });
                case 'yesno':
                    return this.say({
                        text: field.ask,
                        chips: [
                            { label: this.t.yes, value: true, handle: field.handle, action: 'answer', primary: true },
                            { label: this.t.no, value: false, handle: field.handle, action: 'answer' },
                        ],
                    });
                case 'files':
                    return this.askFiles(field);
                default:
                    this.say({
                        text: intro,
                        chips: field.required ? [] : [{ label: this.t.skip, handle: field.handle, action: 'skip' }],
                    });
                    this.expect(field);
            }
        },

        askGroup(step) {
            const values = Object.fromEntries(
                step.map((field) => [
                    field.handle,
                    this.flow.values[field.handle] ?? (field.kind === 'consent' ? false : ''),
                ]),
            );
            const intro = step.some((field) => field.handle === 'email')
                ? this.t.ask.contact_group
                : step.every((field) => field.kind === 'consent')
                  ? this.t.ask.consents_group
                  : step.map((field) => field.ask).join(' ');

            this.say({ text: intro });
            this.say({ card: 'group', data: { fields: step, values, errors: {} } });
        },

        askFiles(field) {
            const count = this.files[field.handle]?.length ?? 0;
            const chips = [];

            if (count < field.max_files) {
                chips.push({
                    label: count ? this.t.add_more : this.t.add_file,
                    handle: field.handle,
                    action: 'file',
                    accept: field.accept,
                    primary: true,
                });
            }

            chips.push(
                count
                    ? { label: this.t.next, handle: field.handle, action: 'skip' }
                    : { label: this.t.skip, handle: field.handle, action: 'skip' },
            );

            this.say({ text: count ? this.tr('files_added', { count }) : field.ask, chips });
        },

        expect(field) {
            this.inputMode = field.kind === 'textarea' ? 'textarea' : field.integer ? 'number' : 'text';
            this.expecting = field.handle;
            this.$nextTick(() => this.focusInput());
        },

        answerText(text) {
            const field = this.fields()[this.expecting];

            this.echo(text);

            const error = this.validate(field, text);

            if (error) {
                return this.say({ text: error });
            }

            this.store(field.handle, text);
        },

        confirmMulti(message) {
            const chosen = message.chips.filter((chip) => chip.action === 'toggle' && chip.selected);
            const field = this.fields()[message.handle];

            if (field.required && chosen.length === 0) {
                return this.say({ text: this.t.choose_atleast_one });
            }

            this.echo(chosen.map((chip) => chip.label).join(', ') || this.t.skip);
            this.store(
                field.handle,
                chosen.map((chip) => chip.value),
            );
        },

        submitGroup(message) {
            const { fields, values } = message.data;
            const errors = {};

            fields.forEach((field) => {
                const error = this.validate(field, values[field.handle]);
                if (error) errors[field.handle] = error;
            });

            message.data.errors = errors;

            if (Object.keys(errors).length) return;

            message.locked = true;
            fields.forEach((field) => this.remember(field.handle, values[field.handle]));
            this.echo(
                fields
                    .filter((field) => field.kind !== 'consent')
                    .map((field) => this.display(field, values[field.handle]))
                    .filter(Boolean)
                    .join(' · ') || this.t.yes,
            );
            this.advance();
        },

        store(handle, value) {
            this.remember(handle, value);

            // Na het verhaal van de klant eerst advies op maat, pas dan de volgende vraag.
            if (handle === 'project' && value && this.cfg.usesModel && !this.flow.editing) {
                return this.ask(value, { mode: 'advice', echo: false });
            }

            this.advance();
        },

        remember(handle, value) {
            this.flow.values[handle] = value;
            if (!this.flow.asked.includes(handle)) this.flow.asked.push(handle);
            this.inputMode = 'none';
            this.expecting = null;
        },

        advance() {
            if (this.flow.editing) {
                this.flow.editing = false;
                return this.showSummary();
            }

            this.nextStep();
        },

        validate(field, value) {
            const text = typeof value === 'string' ? value.trim() : value;
            const empty =
                text === undefined ||
                text === null ||
                text === '' ||
                text === false ||
                (Array.isArray(text) && !text.length);

            if (empty) return field.required ? this.t.required : null;
            if (field.kind === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(text)) return this.t.invalid_email;
            if (field.kind === 'phone' && !isPhone(text)) return this.t.invalid_phone;

            const year = new Date().getFullYear();
            if (field.year && !(/^\d{4}$/.test(text) && +text >= 1950 && +text <= year))
                return this.tr('invalid_year', { year });

            if (
                field.integer &&
                !(
                    /^-?\d+$/.test(text) &&
                    (field.min === undefined || +text >= field.min) &&
                    (field.max === undefined || +text <= field.max)
                )
            ) {
                return this.tr('invalid_number', { min: field.min ?? '', max: field.max ?? '' });
            }

            return null;
        },

        photoChosen(event) {
            const handle = this.pendingFile;
            const field = this.fields()[handle];
            const chosen = [...(event.target.files ?? [])];
            event.target.value = '';

            if (!field || !chosen.length) return;

            const current = this.files[handle] ?? [];
            const room = field.max_files - current.length;
            this.files[handle] = [...current, ...chosen.slice(0, room)];

            chosen.slice(0, room).forEach((file) => {
                this.push({
                    from: 'user',
                    photo: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                    file: file.name,
                });
            });

            if (chosen.length > room) this.say({ text: this.tr('files_max', { max: field.max_files }) });

            this.askFiles(field);
        },

        sortOptions(field) {
            if (!['location', 'branch'].includes(field.handle)) return field.options;

            const nearest = this.nearestShowroom();
            if (!nearest) return field.options;

            return [...field.options]
                .sort((a, b) => (b.value === nearest) - (a.value === nearest))
                .map((option) =>
                    option.value === nearest ? { ...option, label: `${option.label} · ${this.t.nearest}` } : option,
                );
        },

        nearestShowroom() {
            const postcode = String(this.flow?.values.address ?? '').match(/\b(\d{4})\b/)?.[1];
            if (!postcode) return null;

            const rules = Object.entries(this.cfg.postcodeRules ?? {}).sort(([a], [b]) => b.length - a.length);

            return rules.find(([prefix]) => postcode.startsWith(prefix))?.[1] ?? null;
        },

        display(field, value) {
            if (value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length))
                return '';
            const labels = Object.fromEntries((field.options ?? []).map((option) => [option.value, option.label]));

            switch (field.kind) {
                case 'choice':
                    return labels[value] ?? value;
                case 'multi':
                    return value.map((item) => labels[item] ?? item).join(', ');
                case 'yesno':
                case 'consent':
                    return value ? this.t.yes : this.t.no;
                case 'product':
                    return this.cfg.products.find((product) => product.id === value)?.title ?? '';
                default:
                    return String(value);
            }
        },

        showSummary() {
            this.inputMode = 'none';
            const rows = this.steps()
                .flat()
                .filter((field) => field.kind !== 'consent')
                .map((field) => ({
                    handle: field.handle,
                    label: field.label,
                    value:
                        field.kind === 'files'
                            ? (this.files[field.handle] ?? []).map((file) => file.name).join(', ')
                            : this.display(field, this.flow.values[field.handle]),
                    editable: field.kind !== 'product',
                }))
                .filter((row) => row.value);

            this.say({ card: 'summary', data: { rows, form: this.flow.form } });
        },

        edit(message, handle) {
            if (message.locked || this.submitting) return;

            message.locked = true;
            this.flow.editing = true;
            const steps = this.steps();
            this.flow.index = steps.findIndex((step) => step.some((field) => field.handle === handle));

            if (this.fields()[handle].kind === 'files') this.files[handle] = [];

            this.askStep(steps[this.flow.index].filter((field) => field.kind !== 'product'));
        },

        async ensureSession(force = false) {
            if (this.session && !force) return this.session;

            const response = await fetch(this.cfg.endpoints.session, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            this.session = await response.json();

            return this.session;
        },

        async recaptchaToken(key) {
            if (!key) return null;

            if (!window.grecaptcha?.enterprise) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = `https://www.google.com/recaptcha/enterprise.js?render=${key}`;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            return new Promise((resolve) => {
                window.grecaptcha.enterprise.ready(() => {
                    window.grecaptcha.enterprise.execute(key, { action: 'submit' }).then(resolve, () => resolve(null));
                });
            });
        },

        async submit(message, retried = false) {
            if (this.submitting) return;

            this.submitting = true;
            const handle = this.flow.form;
            const form = this.cfg.forms[handle];

            try {
                const session = await this.ensureSession(retried);
                const body = new FormData();
                body.append('_token', session.token);
                body.append('_loaded', session.loaded);

                const captcha = await this.recaptchaToken(session.recaptcha).catch(() => null);
                if (captcha) body.append('g-recaptcha-response', captcha);

                Object.entries(this.fields()).forEach(([name, field]) => {
                    const value = this.flow.values[name];

                    if (field.kind === 'files') {
                        (this.files[name] ?? []).forEach((file) => body.append(`${name}[]`, file));
                    } else if (field.kind === 'multi') {
                        (value ?? []).forEach((item) => body.append(`${name}[]`, item));
                    } else if (field.kind === 'consent' || field.kind === 'yesno') {
                        if (value) body.append(name, '1');
                    } else if (value !== undefined && value !== null && value !== '') {
                        body.append(name, String(value).trim());
                    }
                });

                body.append(
                    '_leadbot',
                    JSON.stringify({
                        transcript: this.transcript(),
                        page: location.href,
                        tracking: storage('get', 'leadbot_tracking') ?? {},
                        locale: this.cfg.locale,
                    }),
                );

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body,
                });

                if (response.status === 419 && !retried) {
                    this.submitting = false;
                    return this.submit(message, true);
                }

                if (response.status === 400 || response.status === 422) {
                    const result = await response.json();
                    const errors =
                        result.error ??
                        Object.fromEntries(Object.entries(result.errors ?? {}).map(([key, list]) => [key, list[0]]));
                    const [field, error] =
                        Object.entries(errors).map(([key, text]) => [key.split('.')[0], text])[0] ?? [];

                    if (field && this.fields()[field]) {
                        this.say({ text: this.tr('field_error', { message: error }) });
                        this.submitting = false;
                        return this.edit(message, field);
                    }

                    throw new Error('validation');
                }

                if (!response.ok) throw new Error(response.status);

                const result = await response.json();
                message.locked = true;

                if (!result.submission_created) {
                    this.say({ text: this.t.rejected, card: 'showrooms' });
                    return;
                }

                this.finish(handle);
            } catch {
                this.say({ text: this.t.error, chips: [{ label: this.t.show_showrooms, action: 'showrooms' }] });
            } finally {
                this.submitting = false;
                this.persist();
            }
        },

        finish(handle) {
            const values = this.flow.values;
            const showroom = this.cfg.showrooms.find(
                (candidate) => candidate.slug === (values.location ?? values.branch),
            );
            const name = String(values.name ?? '')
                .trim()
                .split(' ')[0];

            this.messages.forEach((message) => (message.locked = true));
            this.say({
                card: 'done',
                data: {
                    title: this.t[`done_${handle}_title`],
                    text: this.tr(`done_${handle}`, { name, showroom: showroom?.name ?? 'Winsol Brebo' }),
                    phone: showroom?.phone,
                },
            });
            this.say({
                text: this.t.done_more,
                chips: this.actionChips(this.page.range).filter((chip) => chip.value !== handle),
            });

            this.track('form_submit_success', { form_id: handle, via: 'assistent' });
            this.track('leadbot_lead', { form: handle });

            this.flow = null;
            this.files = {};
            this.session = null;
            this.inputMode = 'question';
        },

        transcript() {
            return this.messages
                .filter((message) => message.text && !message.thinking)
                .map((message) => ({ role: message.from === 'user' ? 'user' : 'bot', text: message.text }))
                .slice(-60);
        },

        // Helpdesk

        async loadHelpdesk() {
            if (this.helpdesk.loaded) return;

            try {
                const response = await fetch(this.cfg.endpoints.helpdesk, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                Object.assign(this.helpdesk, { items: data.items, categories: data.categories, loaded: true });
            } catch {
                this.helpdesk.loaded = false;
            }
        },

        helpdeskResults() {
            const words = normalize(this.helpdesk.query)
                .split(/[^a-z0-9]+/)
                .filter((word) => word.length > 2);

            return this.helpdesk.items
                .filter((item) => !this.helpdesk.category || item.category === this.helpdesk.category)
                .map((item) => {
                    const haystack = normalize(`${item.question} ${item.question} ${item.keywords} ${item.answer}`);
                    const score = words.reduce(
                        (total, word) =>
                            total + (haystack.includes(word.slice(0, Math.max(4, word.length - 2))) ? 1 : 0),
                        0,
                    );
                    return { item, score };
                })
                .filter(({ score }) => !words.length || score > 0)
                .sort((a, b) => b.score - a.score)
                .map(({ item }) => item);
        },

        helpdeskCategories() {
            const used = new Set(this.helpdesk.items.map((item) => item.category));
            return Object.entries(this.helpdesk.categories).filter(([key]) => used.has(key));
        },

        voteHelpdesk(item, helpful) {
            this.helpdesk.voted[item.id] = helpful ? 'up' : 'down';
            fetch(this.cfg.endpoints.feedback, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ helpful }),
            }).catch(() => {});
        },

        askAssistant(question) {
            this.show('chat');
            this.ask(question);
        },

        // Showrooms en openingsuren

        now() {
            const parts = Object.fromEntries(
                new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Europe/Brussels',
                    weekday: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                    hourCycle: 'h23',
                })
                    .formatToParts(new Date())
                    .map((part) => [part.type, part.value]),
            );

            return {
                day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday),
                time: `${parts.hour}:${parts.minute}`,
            };
        },

        showroomState(showroom) {
            const { day, time } = this.now();
            const today = showroom.schedule[day];

            if (Array.isArray(today) && time >= today[0] && time < today[1]) {
                return { tone: 'open', label: this.t.open_now, until: today[1] };
            }

            if (today === 'appointment') return { tone: 'appointment', label: this.t.by_appointment };

            return { tone: 'closed', label: this.t.closed };
        },

        overallStatus() {
            const states = this.cfg.showrooms.map((showroom) => ({ showroom, state: this.showroomState(showroom) }));
            const open = states.find(({ state }) => state.tone === 'open');

            if (open) {
                return {
                    tone: 'open',
                    text: this.tr('open_until', {
                        name: open.showroom.name.replace('Winsol ', ''),
                        time: open.state.until,
                    }),
                };
            }

            if (states.some(({ state }) => state.tone === 'appointment')) {
                return { tone: 'appointment', text: this.t.appointment_today };
            }

            return { tone: 'closed', text: this.t.closed_now };
        },

        // Context, bewaren en meten

        detectPage() {
            const path = location.pathname.replace(/\/$/, '');
            const pathOf = (url) => new URL(url, location.origin).pathname.replace(/\/$/, '');
            const product = this.cfg.products.find((candidate) => pathOf(candidate.url) === path) ?? null;
            const range =
                (product && this.cfg.ranges.find((candidate) => candidate.handle === product.range)) ??
                this.cfg.ranges.find(
                    (candidate) => path === pathOf(candidate.url) || path.startsWith(`${pathOf(candidate.url)}/`),
                ) ??
                null;

            return { range, product };
        },

        persist() {
            storage('set', STORAGE_KEY, {
                locale: this.cfg.locale,
                open: this.open,
                view: this.view,
                counter: this.counter,
                flow: this.flow,
                expecting: this.expecting,
                inputMode: this.inputMode,
                messages: this.messages
                    .filter((message) => !message.thinking)
                    .map((message) => ({ ...message, photo: null, typing: false, tokens: null }))
                    .slice(-80),
            });
        },

        /**
         * Een gesprek in een andere taal begint opnieuw: de knoppen en de
         * formulieren horen bij de taal van de pagina.
         */
        restore() {
            const saved = storage('get', STORAGE_KEY);

            if (!saved || saved.locale !== this.cfg.locale) return;

            Object.assign(this, {
                open: saved.open,
                view: saved.view,
                counter: saved.counter,
                messages: saved.messages,
                flow: saved.flow,
                expecting: saved.expecting,
                inputMode: saved.inputMode,
            });

            // Bestanden overleven een paginawissel niet; die stap komt dan opnieuw.
            if (this.flow) {
                const fileFields = Object.values(this.fields())
                    .filter((field) => field.kind === 'files')
                    .map((field) => field.handle);
                this.flow.asked = this.flow.asked.filter((handle) => !fileFields.includes(handle));
            }

            if (this.open) {
                document.documentElement.classList.add('leadbot-locked');
                this.$nextTick(() => this.scrollDown(false));
            }
        },

        /**
         * De klik-id's uit de landings-URL bewaren tot de aanvraag vertrekt.
         * Ze reizen mee met de inzending, zodat een getekende offerte later
         * als offline conversie naar Google Ads kan.
         */
        captureTracking() {
            const params = new URLSearchParams(location.search);
            const stored = storage('get', 'leadbot_tracking') ?? {};
            const found = Object.fromEntries(
                TRACKING_KEYS.filter((key) => params.get(key)).map((key) => [key, params.get(key)]),
            );

            if (!stored.landing) {
                found.landing = location.pathname;
                found.referrer = document.referrer ? new URL(document.referrer).hostname : '';
            }

            storage('set', 'leadbot_tracking', { ...stored, ...found });
        },

        track(event, data = {}) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event, ...data });
        },
    };
}
