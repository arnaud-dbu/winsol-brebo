# Mapping: oude adressen naar nieuwe adressen

Beslisdocument bij `spec.md`. Geen code: dit is de uitkomst van het naast elkaar
leggen van de sitemap van de oude site (1674 adressen, opgehaald 2026-09-09) en
de adressen van de nieuwe site (153).

Sleutels zijn kleine letters zonder afsluitende slash. De oude site serveerde
dezelfde pagina onder verschillende hoofdletters, bijvoorbeeld
`/nl/Ons-aanbod/Stalen-deuren` naast `/nl/ons-aanbod/stalen-deuren`. Door alles
te normaliseren vallen die varianten op dezelfde regel.

## De klimregel vervangt de patroonregels

De spec beschrijft drie lagen: exacte regels, twaalf patroonregels voor de
gemeentepagina's, en een klimregel als vangnet. Bij het uitwerken bleek de
tweede laag overbodig. `/winsol-ramen/aartselaar` vindt geen eigen regel, klimt
naar `/winsol-ramen`, en die staat gewoon als normale regel in de tabel. Eén
tabel en één algoritme in plaats van drie lagen.

De twaalf productgroepwortels onderaan de eerste tabelsectie dekken op die
manier alle 1512 gemeentepagina's: zes Nederlandse en zes Franse, elk 126
gemeenten.

De klim stopt bij het laatste segment en klimt dus nooit door naar de wortel.
Alles naar de homepage sturen leest Google als soft 404, en zet de bezoeker op
een pagina die zijn vraag niet raakt. Een 404 is dan eerlijker.

## Beslissingen die niet vanzelf spreken

| Oud | Nieuw | Waarom |
| --- | --- | --- |
| `/nl/ons-aanbod/rolluiken/garagerolluiken` | `/aanbod/garagepoorten/garagerolluiken` | stond onder rolluiken, hoort nu bij de garagepoorten |
| `/nl/ons-aanbod/garagepoorten/schuifpoorten` | `/aanbod/garagepoorten/sectionale-schuifpoorten` | hernoemd product |
| `/nl/ons-aanbod/stalen-deuren` | `/aanbod/stalen-binnendeuren` | hernoemd product |
| `/nl/ons-aanbod/terrasoverkapping/win-cube` | `/aanbod/terrasoverkapping/wincube` | streepje weg in de nieuwe slug |
| `/nl/screens-verticale-zonwering` | `/aanbod/zonwering/screens` | losse landingspagina, nu een product |
| `/nl/inspiratie` | `/realisaties` | zelfde bedoeling, andere naam |
| `/nl/vacatures` | `/over-ons` | geen vacaturepagina meer |
| `/nl/ons-aanbod/airco` | `/aanbod` | uit het aanbod |
| `/nl/ons-aanbod/terrasoverkapping/patiola` | `/aanbod/terrasoverkapping` | uit het aanbod |
| `/nl/ons-aanbod/zonwering/configurator` | `/aanbod/zonwering` | bestaat niet meer |
| `/nl/ons-aanbod/ramen-en-deuren/veiligheidsdeuren` | `/aanbod/ramen-en-deuren` | geen eigen product meer |
| `/fr/notre-gamme/portes-et-fenetres/portes-et-fenetres-en-pvc` | `/fr/gamme/chassis-et-portes` | de oude pagina behandelde ramen én deuren; de nieuwe site splitst dat, dus één van de twee zou de helft van de bezoekers verkeerd zetten |
| `/fr/notre-gamme/volets-roulants/volets-roulants-mini-caisson` | `/fr/gamme/volets-roulants/volets-a-caisson-apparent` | zelfde product, andere Franse naam |
| `/fr/notre-gamme/volets-roulants/volets-roulants-superposes` | `/fr/gamme/volets-roulants/volets-en-applique` | zelfde product, andere Franse naam |
| `/fr/notre-gamme/volets-roulants/volets-roulants-interieurs` | `/fr/gamme/volets-roulants/volets-encastres` | zelfde product, andere Franse naam |
| `/fr/notre-gamme/protections-solaires/protection-verticale` | `/fr/gamme/protection-solaire/screens` | zelfde product, andere Franse naam |
| `/fr/notre-gamme/protections-solaires/stores-bannes` | `/fr/gamme/protection-solaire/tentes-solaires` | zelfde product, andere Franse naam |
| `/fr/notre-gamme/portes-de-garage/porte-de-garage-a-deplacement-lateral` | `/fr/gamme/portes-de-garage/portes-sectionnelles-coulissantes` | zelfde product, andere Franse naam |

## Brochureadressen

De oude site had per product een aparte downloadpagina per brochure. Die gaan
allemaal naar `/brochures`, of `/fr/brochures` bij een Frans pad. Zonder deze
regel zou zo'n adres naar de productpagina klimmen: het juiste onderwerp, maar
niet wat de bezoeker kwam halen.

Herkenbaar aan het laatste segment:

```
download-brochure-berner                        depliant-pergola-so
download-brochure-garagepoorten                 depliant-portes-de-garage
download-brochure-genieten-van-outdoor-living   depliant-portes-et-fenetres
download-brochure-pergola-so                    depliant-protections-solaires
download-brochure-pergola-zip-en-zip-cube-nl
download-brochure-pergola-zip-en-zip-cube-fr
download-brochure-ramen-en-deuren
download-brochure-rolluiken-nl
download-brochure-rolluiken-fr
download-brochure-steel-design
download-brochure-zonwering
```

## De volledige tabel

| Oud pad | Nieuw pad |
| --- | --- |
| `(wortel)` | `/` |
| `/winsol-ramen` | `/aanbod/ramen-en-deuren` |
| `/winsol-deuren` | `/aanbod/ramen-en-deuren` |
| `/winsol-rolluiken` | `/aanbod/rolluiken` |
| `/winsol-zonwering` | `/aanbod/zonwering` |
| `/winsol-terrasoverkapping` | `/aanbod/terrasoverkapping` |
| `/winsol-velux` | `/aanbod/velux` |
| `/fenetres-winsol` | `/fr/gamme/chassis-et-portes` |
| `/portes-winsol` | `/fr/gamme/chassis-et-portes` |
| `/volets-roulants-winsol` | `/fr/gamme/volets-roulants` |
| `/protections-solaires-winsol` | `/fr/gamme/protection-solaire` |
| `/couvertures-de-terrasse-winsol` | `/fr/gamme/couverture-de-terrasse` |
| `/velux-winsol` | `/fr/gamme/fenetres-de-toit-velux` |
| `/nl` | `/` |
| `/nl/home` | `/` |
| `/nl/contact` | `/contact` |
| `/nl/over-ons` | `/over-ons` |
| `/nl/inspiratie` | `/realisaties` |
| `/nl/simuleer-je-lening` | `/simuleer-je-lening` |
| `/nl/screens-verticale-zonwering` | `/aanbod/zonwering/screens` |
| `/nl/vacatures` | `/over-ons` |
| `/nl/ons-aanbod` | `/aanbod` |
| `/nl/ons-aanbod/somfy-smart-home` | `/aanbod/somfy-smart-home` |
| `/nl/ons-aanbod/velux` | `/aanbod/velux` |
| `/nl/ons-aanbod/airco` | `/aanbod` |
| `/nl/ons-aanbod/garagepoorten` | `/aanbod/garagepoorten` |
| `/nl/ons-aanbod/garagepoorten/garagerolluiken` | `/aanbod/garagepoorten/garagerolluiken` |
| `/nl/ons-aanbod/garagepoorten/sectionale-poorten` | `/aanbod/garagepoorten/sectionale-poorten` |
| `/nl/ons-aanbod/garagepoorten/schuifpoorten` | `/aanbod/garagepoorten/sectionale-schuifpoorten` |
| `/nl/ons-aanbod/garagepoorten/somfy-smart-home` | `/aanbod/somfy-smart-home` |
| `/nl/ons-aanbod/ramen-en-deuren` | `/aanbod/ramen-en-deuren` |
| `/nl/ons-aanbod/ramen-en-deuren/aluminium-ramen` | `/aanbod/ramen-en-deuren/aluminium-ramen` |
| `/nl/ons-aanbod/ramen-en-deuren/aluminium-ramen-en-deuren` | `/aanbod/ramen-en-deuren/aluminium-ramen-en-deuren` |
| `/nl/ons-aanbod/ramen-en-deuren/pvc-ramen` | `/aanbod/ramen-en-deuren/pvc-ramen` |
| `/nl/ons-aanbod/ramen-en-deuren/pvc-deuren` | `/aanbod/ramen-en-deuren/pvc-deuren` |
| `/nl/ons-aanbod/ramen-en-deuren/sierluiken` | `/aanbod/ramen-en-deuren/sierluiken` |
| `/nl/ons-aanbod/ramen-en-deuren/steellook` | `/aanbod/ramen-en-deuren/steellook` |
| `/nl/ons-aanbod/ramen-en-deuren/vliegenramen` | `/aanbod/ramen-en-deuren/vliegenramen` |
| `/nl/ons-aanbod/ramen-en-deuren/veiligheidsdeuren` | `/aanbod/ramen-en-deuren` |
| `/nl/ons-aanbod/rolluiken` | `/aanbod/rolluiken` |
| `/nl/ons-aanbod/rolluiken/inbouwrolluiken` | `/aanbod/rolluiken/inbouwrolluiken` |
| `/nl/ons-aanbod/rolluiken/opbouwrolluiken` | `/aanbod/rolluiken/opbouwrolluiken` |
| `/nl/ons-aanbod/rolluiken/voorzetrolluiken` | `/aanbod/rolluiken/voorzetrolluiken` |
| `/nl/ons-aanbod/rolluiken/rolluiken-met-fusion-lamellen` | `/aanbod/rolluiken/rolluiken-met-fusion-lamellen` |
| `/nl/ons-aanbod/rolluiken/rolluiken-op-zonne-energie` | `/aanbod/rolluiken/rolluiken-op-zonne-energie` |
| `/nl/ons-aanbod/rolluiken/garagerolluiken` | `/aanbod/garagepoorten/garagerolluiken` |
| `/nl/ons-aanbod/stalen-deuren` | `/aanbod/stalen-binnendeuren` |
| `/nl/ons-aanbod/terrasoverkapping` | `/aanbod/terrasoverkapping` |
| `/nl/ons-aanbod/terrasoverkapping/pergola-so` | `/aanbod/terrasoverkapping/pergola-so` |
| `/nl/ons-aanbod/terrasoverkapping/pergola-zip` | `/aanbod/terrasoverkapping/pergola-zip` |
| `/nl/ons-aanbod/terrasoverkapping/pergola-zip-cube` | `/aanbod/terrasoverkapping/pergola-zip-cube` |
| `/nl/ons-aanbod/terrasoverkapping/win-cube` | `/aanbod/terrasoverkapping/wincube` |
| `/nl/ons-aanbod/terrasoverkapping/somfy-smart-home` | `/aanbod/somfy-smart-home` |
| `/nl/ons-aanbod/terrasoverkapping/patiola` | `/aanbod/terrasoverkapping` |
| `/nl/ons-aanbod/zonwering` | `/aanbod/zonwering` |
| `/nl/ons-aanbod/zonwering/solarfix` | `/aanbod/zonwering/solarfix` |
| `/nl/ons-aanbod/zonwering/verandazonwering` | `/aanbod/zonwering/verandazonwering` |
| `/nl/ons-aanbod/zonwering/zonneschermen` | `/aanbod/zonwering/zonneschermen` |
| `/nl/ons-aanbod/zonwering/terrasoverkapping` | `/aanbod/terrasoverkapping` |
| `/nl/ons-aanbod/zonwering/configurator` | `/aanbod/zonwering` |
| `/nl/wij-plaatsen-ramen-in-jouw-gemeente` | `/aanbod/ramen-en-deuren` |
| `/nl/wij-plaatsen-deuren-in-jouw-gemeente` | `/aanbod/ramen-en-deuren` |
| `/nl/wij-plaatsen-rolluiken-in-jouw-gemeente` | `/aanbod/rolluiken` |
| `/nl/wij-plaatsen-zonweringen-in-jouw-gemeente` | `/aanbod/zonwering` |
| `/nl/wij-plaatsen-terrasoverkappingen-in-jouw-gemeente` | `/aanbod/terrasoverkapping` |
| `/nl/wij-plaatsen-velux-in-jouw-gemeente` | `/aanbod/velux` |
| `/nl/winsol-ramen` | `/aanbod/ramen-en-deuren` |
| `/nl/winsol-deuren` | `/aanbod/ramen-en-deuren` |
| `/nl/winsol-rolluiken` | `/aanbod/rolluiken` |
| `/nl/winsol-zonwering` | `/aanbod/zonwering` |
| `/nl/winsol-terrasoverkapping` | `/aanbod/terrasoverkapping` |
| `/nl/winsol-velux` | `/aanbod/velux` |
| `/fr` | `/fr` |
| `/fr/accueil` | `/fr` |
| `/fr/a-propos-de-nous` | `/fr/a-propos` |
| `/fr/contact` | `/fr/contact` |
| `/fr/realisations` | `/fr/realisations` |
| `/fr/simulez-votre-pret` | `/fr/simulez-votre-pret` |
| `/fr/vacatures` | `/fr/a-propos` |
| `/fr/notre-gamme` | `/fr/gamme` |
| `/fr/notre-gamme/airco` | `/fr/gamme` |
| `/fr/notre-gamme/somfy-smart-home` | `/fr/gamme/somfy-smart-home` |
| `/fr/notre-gamme/velux` | `/fr/gamme/fenetres-de-toit-velux` |
| `/fr/notre-gamme/couvertures-de-terrasse` | `/fr/gamme/couverture-de-terrasse` |
| `/fr/notre-gamme/couvertures-de-terrasse/pergola-so` | `/fr/gamme/couverture-de-terrasse/pergola-so` |
| `/fr/notre-gamme/couvertures-de-terrasse/pergola-zip` | `/fr/gamme/couverture-de-terrasse/pergola-zip` |
| `/fr/notre-gamme/couvertures-de-terrasse/pergola-zip-cube` | `/fr/gamme/couverture-de-terrasse/pergola-zip-cube` |
| `/fr/notre-gamme/couvertures-de-terrasse/win-cube` | `/fr/gamme/couverture-de-terrasse/wincube` |
| `/fr/notre-gamme/couvertures-de-terrasse/somfy-smart-home` | `/fr/gamme/somfy-smart-home` |
| `/fr/notre-gamme/portes-de-garage` | `/fr/gamme/portes-de-garage` |
| `/fr/notre-gamme/portes-de-garage/portes-de-garage-sectionnelle` | `/fr/gamme/portes-de-garage/portes-de-garage-sectionnelles` |
| `/fr/notre-gamme/portes-de-garage/porte-de-garage-a-deplacement-lateral` | `/fr/gamme/portes-de-garage/portes-sectionnelles-coulissantes` |
| `/fr/notre-gamme/portes-de-garage/volets-de-garage` | `/fr/gamme/portes-de-garage/volets-de-garage` |
| `/fr/notre-gamme/portes-de-garage/somfy-smart-home` | `/fr/gamme/somfy-smart-home` |
| `/fr/notre-gamme/portes-en-acier` | `/fr/gamme/portes-interieur-acier` |
| `/fr/notre-gamme/portes-et-fenetres` | `/fr/gamme/chassis-et-portes` |
| `/fr/notre-gamme/portes-et-fenetres/moustiquaires` | `/fr/gamme/chassis-et-portes/moustiquaires` |
| `/fr/notre-gamme/portes-et-fenetres/steellook` | `/fr/gamme/chassis-et-portes/steellook` |
| `/fr/notre-gamme/portes-et-fenetres/volets-decoratifs` | `/fr/gamme/chassis-et-portes/volets-battants-decoratifs` |
| `/fr/notre-gamme/portes-et-fenetres/portes-et-fenetres-en-aluminium` | `/fr/gamme/chassis-et-portes/chassis-et-portes-en-aluminium` |
| `/fr/notre-gamme/portes-et-fenetres/portes-et-fenetres-en-pvc` | `/fr/gamme/chassis-et-portes` |
| `/fr/notre-gamme/portes-et-fenetres/portes-de-securite` | `/fr/gamme/chassis-et-portes` |
| `/fr/notre-gamme/protections-solaires` | `/fr/gamme/protection-solaire` |
| `/fr/notre-gamme/protections-solaires/solarfix` | `/fr/gamme/protection-solaire/solarfix` |
| `/fr/notre-gamme/protections-solaires/protection-solaire-pour-veranda` | `/fr/gamme/protection-solaire/protection-solaire-pour-veranda` |
| `/fr/notre-gamme/protections-solaires/protection-verticale` | `/fr/gamme/protection-solaire/screens` |
| `/fr/notre-gamme/protections-solaires/stores-bannes` | `/fr/gamme/protection-solaire/tentes-solaires` |
| `/fr/notre-gamme/protections-solaires/configurateur` | `/fr/gamme/protection-solaire` |
| `/fr/notre-gamme/volets-roulants` | `/fr/gamme/volets-roulants` |
| `/fr/notre-gamme/volets-roulants/volets-roulants-interieurs` | `/fr/gamme/volets-roulants/volets-encastres` |
| `/fr/notre-gamme/volets-roulants/volets-roulants-mini-caisson` | `/fr/gamme/volets-roulants/volets-a-caisson-apparent` |
| `/fr/notre-gamme/volets-roulants/volets-roulants-superposes` | `/fr/gamme/volets-roulants/volets-en-applique` |
| `/fr/notre-gamme/volets-roulants/volets-roulants-a-lames-fusion` | `/fr/gamme/volets-roulants/volets-a-lames-fusion` |
| `/fr/notre-gamme/volets-roulants/volets-roulants-a-energie-solaire` | `/fr/gamme/volets-roulants/volets-solaires` |
| `/fr/notre-gamme/volets-roulants/volets-de-garage` | `/fr/gamme/portes-de-garage/volets-de-garage` |
| `/fr/nous-installons-des-fenetres-dans-votre-commune` | `/fr/gamme/chassis-et-portes` |
| `/fr/nous-installons-des-portes-dans-votre-commune` | `/fr/gamme/chassis-et-portes` |
| `/fr/nous-installons-des-volets-roulants-dans-votre-commune` | `/fr/gamme/volets-roulants` |
| `/fr/nous-installons-des-protections-solaires-dans-votre-commune` | `/fr/gamme/protection-solaire` |
| `/fr/nous-installons-des-couvertures-de-terrasse-dans-votre-commune` | `/fr/gamme/couverture-de-terrasse` |
| `/fr/nous-installons-des-velux-dans-votre-commune` | `/fr/gamme/fenetres-de-toit-velux` |
| `/fr/fenetres-winsol` | `/fr/gamme/chassis-et-portes` |
| `/fr/portes-winsol` | `/fr/gamme/chassis-et-portes` |
| `/fr/volets-roulants-winsol` | `/fr/gamme/volets-roulants` |
| `/fr/protections-solaires-winsol` | `/fr/gamme/protection-solaire` |
| `/fr/couvertures-de-terrasse-winsol` | `/fr/gamme/couverture-de-terrasse` |
| `/fr/velux-winsol` | `/fr/gamme/fenetres-de-toit-velux` |
