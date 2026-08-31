/*
 * Terugkoppeling op de formulieren, op twee punten waar de bezoeker anders in
 * het ongewisse blijft.
 *
 * De bestandsvelden liggen als een transparante `input[type=file]` over
 * `.form-dropzone`. Daardoor is ook de bestandsnaam die de browser zelf toont
 * onzichtbaar: je klikt, kiest een bestand, en er verandert niets op het
 * scherm. Hier wordt de gekozen naam en grootte dus zelf getoond.
 *
 * En bij het versturen: de bijlagen gaan mee in dezelfde POST, dus met een
 * foto van enkele megabytes duurt dat merkbaar lang. Zonder toestandsverandering
 * op de knop lijkt er niets te gebeuren en klikt men een tweede keer.
 */

const DROPZONE = '.form-dropzone';
const BESTANDSNAAM = '[data-file-name]';

function leesbareGrootte(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const eenheden = ['kB', 'MB'];
    let waarde = bytes / 1024;
    let index = 0;

    while (waarde >= 1024 && index < eenheden.length - 1) {
        waarde /= 1024;
        index++;
    }

    return `${waarde.toFixed(waarde < 10 ? 1 : 0)} ${eenheden[index]}`;
}

function toonBestand(dropzone) {
    const input = dropzone.querySelector('input[type="file"]');
    const doel = dropzone.querySelector(BESTANDSNAAM);

    if (!input || !doel) {
        return;
    }

    const bestand = input.files && input.files[0];

    dropzone.classList.toggle('has-file', Boolean(bestand));
    doel.textContent = bestand ? `${bestand.name} — ${leesbareGrootte(bestand.size)}` : '';
}

function initDropzones(root) {
    root.querySelectorAll(DROPZONE).forEach((dropzone) => {
        const input = dropzone.querySelector('input[type="file"]');

        if (!input) {
            return;
        }

        // Ook meteen bij het laden: na een validatiefout rendert de pagina
        // opnieuw en houdt de browser de keuze soms vast.
        toonBestand(dropzone);
        input.addEventListener('change', () => toonBestand(dropzone));
    });
}

function initVerzendknop(root) {
    root.querySelectorAll('form.form').forEach((form) => {
        form.addEventListener('submit', () => {
            const knop = form.querySelector('button[type="submit"]');

            // De browser stuurt niets bij een mislukte HTML5-validatie; dan mag
            // de knop ook niet op slot.
            if (!knop || !form.checkValidity()) {
                return;
            }

            knop.disabled = true;
            knop.classList.add('is-sending');
            knop.dataset.label = knop.textContent.trim();
            knop.textContent = knop.dataset.sendingLabel || 'Bezig met verzenden…';
        });
    });
}

export function initFormFeedback(root = document) {
    initDropzones(root);
    initVerzendknop(root);
}

initFormFeedback();
