/*
 * Terugkoppeling op de formulieren, op drie punten waar de bezoeker anders in
 * het ongewisse blijft.
 *
 * De bestandsvelden liggen als een transparante `input[type=file]` over
 * `.form-dropzone`. Daardoor is ook de bestandsnaam die de browser zelf toont
 * onzichtbaar: je klikt, kiest een bestand, en er verandert niets op het
 * scherm. Hier wordt de keuze dus zelf getoond.
 *
 * De grenzen worden hier ook bewaakt. Gaat een POST over `post_max_size`, dan
 * gooit PHP de body én $_POST weg: geen validatiefout, geen melding, alleen
 * een leeg formulier. Dat moet de browser opvangen vóór het versturen. De
 * getallen komen als data-attributen mee uit uploadField.antlers.html, zodat
 * ze niet uit elkaar kunnen lopen met wat de server werkelijk aanneemt.
 *
 * En bij het versturen: de bijlagen gaan mee in dezelfde POST, dus met enkele
 * foto's duurt dat merkbaar lang. Zonder toestandsverandering op de knop lijkt
 * er niets te gebeuren en klikt men een tweede keer.
 */

const DROPZONE = '.form-dropzone';
const BESTANDSNAAM = '[data-file-name]';
const FOUTMELDING = '[data-upload-error]';

function taal() {
    return document.documentElement.lang || 'nl';
}

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

    const decimalen = waarde < 10 ? 1 : 0;

    return `${waarde.toLocaleString(taal(), {
        minimumFractionDigits: decimalen,
        maximumFractionDigits: decimalen,
    })} ${eenheden[index]}`;
}

function vertaal(sleutel, vervangingen = {}) {
    const woordenboek = {
        nl: {
            bestanden: ':aantal bestanden',
            teVeel: 'Je koos :gekozen bestanden. Er kunnen er hoogstens :max mee.',
            teGroot: ':naam is :grootte. Per bestand kan er hoogstens :max mee.',
            samenTeGroot: 'Samen is dit :totaal. Alle bestanden samen kunnen hoogstens :max wegen.',
        },
        fr: {
            bestanden: ':aantal fichiers',
            teVeel: 'Vous avez choisi :gekozen fichiers. :max au maximum.',
            teGroot: ':naam pèse :grootte. Chaque fichier peut peser :max au maximum.',
            samenTeGroot: "Au total cela fait :totaal. L'ensemble des fichiers peut peser :max au maximum.",
        },
        en: {
            bestanden: ':aantal files',
            teVeel: 'You picked :gekozen files. :max at most.',
            teGroot: ':naam is :grootte. Each file can be :max at most.',
            samenTeGroot: 'Together that is :totaal. All files together can be :max at most.',
        },
    };

    const taalcode = taal().slice(0, 2);
    const regels = woordenboek[taalcode] || woordenboek.nl;

    return Object.entries(vervangingen).reduce(
        (tekst, [sleutelnaam, waarde]) => tekst.replaceAll(`:${sleutelnaam}`, waarde),
        regels[sleutel] || '',
    );
}

function grenzen(dropzone) {
    const getal = (naam) => Number.parseInt(dropzone.dataset[naam] || '0', 10) || 0;

    return {
        perBestand: getal('maxFile'),
        totaal: getal('maxTotal'),
        aantal: getal('maxFiles'),
    };
}

/**
 * Geeft de foutmelding terug, of een lege string als de keuze past.
 */
function controleer(bestanden, limiet) {
    if (limiet.aantal && bestanden.length > limiet.aantal) {
        return vertaal('teVeel', { gekozen: bestanden.length, max: limiet.aantal });
    }

    if (limiet.perBestand) {
        const teGroot = bestanden.find((bestand) => bestand.size > limiet.perBestand);

        if (teGroot) {
            return vertaal('teGroot', {
                naam: teGroot.name,
                grootte: leesbareGrootte(teGroot.size),
                max: leesbareGrootte(limiet.perBestand),
            });
        }
    }

    const totaal = bestanden.reduce((som, bestand) => som + bestand.size, 0);

    if (limiet.totaal && totaal > limiet.totaal) {
        return vertaal('samenTeGroot', {
            totaal: leesbareGrootte(totaal),
            max: leesbareGrootte(limiet.totaal),
        });
    }

    return '';
}

function toonBestanden(dropzone) {
    const input = dropzone.querySelector('input[type="file"]');
    const doel = dropzone.querySelector(BESTANDSNAAM);

    if (!input) {
        return;
    }

    const bestanden = Array.from(input.files || []);
    const totaal = bestanden.reduce((som, bestand) => som + bestand.size, 0);

    dropzone.classList.toggle('has-file', bestanden.length > 0);

    if (doel) {
        // Bij één bestand de naam, bij meer het aantal: vijf namen naast elkaar
        // lezen slechter dan "5 bestanden", en de totale grootte is wat telt.
        const omschrijving =
            bestanden.length === 1 ? bestanden[0].name : vertaal('bestanden', { aantal: bestanden.length });

        doel.textContent = bestanden.length ? `${omschrijving} — ${leesbareGrootte(totaal)}` : '';
    }

    // De melding staat náást de dropzone, niet erin.
    const fout = dropzone.parentElement?.querySelector(FOUTMELDING);
    const melding = controleer(bestanden, grenzen(dropzone));

    // Via de native validatie: dan blokkeert de browser zelf het versturen en
    // laat de submit-handler de knop met rust.
    input.setCustomValidity(melding);

    if (fout) {
        fout.textContent = melding;
        fout.hidden = melding === '';
    }

    input.setAttribute('aria-invalid', melding ? 'true' : 'false');
}

function initDropzones(root) {
    root.querySelectorAll(DROPZONE).forEach((dropzone) => {
        const input = dropzone.querySelector('input[type="file"]');

        if (!input) {
            return;
        }

        // Ook meteen bij het laden: na een validatiefout rendert de pagina
        // opnieuw en houdt de browser de keuze soms vast.
        toonBestanden(dropzone);
        input.addEventListener('change', () => toonBestanden(dropzone));
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
