# Winsol Brebo

De website van Winsol Brebo, verdeler van ramen, deuren, rolluiken, zonwering,
terrasoverkappingen en garagepoorten. Drietalig: Nederlands op de root, Frans
onder `/fr`, Engels onder `/en`.

## Language

### Sites

**Nieuwe site**:
winsol-brebo.be. Deze codebase.
_Avoid_: Brebo, de Statamic-site

**Oude site**:
winsoldilbeek.be. De vorige website, op een eigen server in een ander CMS.
Blijft in de lucht tot alle adressen doorverwijzen naar de nieuwe site.
_Avoid_: Dilbeek, de oude Winsol-site

### Aanbod

**Range**:
Een productgroep, zoals rolluiken of terrasoverkapping. De `ranges`-collectie.
_Avoid_: Categorie, productlijn, assortiment

**Product**:
Een concreet model binnen een range, zoals voorzetrolluiken. De
`products`-collectie.
_Avoid_: Artikel, item

### Locaties

**Showroom**:
Een fysieke vestiging waar klanten binnen kunnen lopen. Er zijn er drie. De
`locations`-collectie.
_Avoid_: Locatie, vestiging, verkooppunt

**Gemeentepagina**:
Een landingspagina van de oude site voor één range in één gemeente, bedoeld om
te scoren op een zoekopdracht als "ramen Aartselaar". Er zijn er 1512. De
nieuwe site heeft ze niet.
_Avoid_: Locatiepagina, lokale pagina, stadspagina

Een gemeentepagina is geen showroom: er zijn drie showrooms en er waren 126
gemeenten.

### Acties

**Actie**:
Een tijdelijke promotie. Is altijd een nieuwsartikel met `promo` aan, nooit een
eigen collectie.
_Avoid_: Promo, campagne, aanbieding
