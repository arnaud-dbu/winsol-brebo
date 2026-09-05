---
id: 8c2e41a0-0008-4a1b-9c7d-3e5f6a7b8c08
blueprint: ranges
title: 'Somfy Smart Home'
short_description: 'Somfy TaHoma laat je rolluiken, zonwering en poort samenwerken via één app, afstandsbediening of je stem.'
long_description: 'Elektrische rolluiken, zonwering of een elektrische poort maken het leven thuis al comfortabeler. Somfy Smart Home zet daar een laag connectiviteit bovenop: alles wat je al hebt, komt op één systeem en luistert naar één app. Je bedient het van waar je ook bent, of je laat het zichzelf bedienen op basis van temperatuur, tijdstip of zonlicht.'
order: 9
image: ranges/somfy-smart-home.png
range_categories:
  - slim-en-comfort
meta_title: 'Somfy Smart Home met TaHoma'
meta_description: 'Somfy TaHoma verbindt je rolluiken, zonwering, verlichting en garagepoort tot één systeem. Compatibel met bijna 300 producttypes en met spraakassistenten.'
seo_noindex: false
page_builder:
  -
    id: sm-systeem
    type: text
    title: 'Eén systeem voor wat er al hangt'
    text:
      -
        type: paragraph
        attrs:
          textAlign: left
        content:
          -
            type: text
            text: 'Heb je elektrische rolluiken, zonwering of een poort, dan zit de motor er al. Somfy TaHoma verbindt die motoren met elkaar en brengt ze samen in één app, zodat je ze niet langer stuk voor stuk hoeft te bedienen.'
      -
        type: paragraph
        attrs:
          textAlign: left
        content:
          -
            type: text
            text: 'Dat werkt niet alleen met Somfy. TaHoma spreekt met bijna driehonderd soorten huishoudelijke producten, en via het So Open-programma komen er elk jaar merken en toestellen bij.'
    enabled: true
  -
    id: sm-redenen
    type: features
    overline: Voordelen
    title: 'Vier goede redenen om je huis slim te maken'
    features:
      -
        id: sm-r1
        type: feature
        icon: squares-four
        title: 'Alles tegelijk'
        text: 'Eén aanraking sluit bij het vertrekken je rolluiken, jaloezieën en verlichting. Bij thuiskomst gaat alles in één beweging weer open.'
        enabled: true
      -
        id: sm-r2
        type: feature
        icon: thermometer-simple
        title: 'Minder energie'
        text: 'De rolluiken sluiten zodra het binnen te warm wordt en openen voor de winterzon. Dat stelt het moment uit waarop de airco aan moet.'
        enabled: true
      -
        id: sm-r3
        type: feature
        icon: device-mobile
        title: 'Van waar je ook bent'
        text: 'Onderweg controleer je de stand van je luiken en open je je poort voor een levering, ook als er niemand thuis is.'
        enabled: true
      -
        id: sm-r4
        type: feature
        icon: shield-check
        title: 'Alsof je thuis bent'
        text: 'De aanwezigheidssimulatie laat luiken en lichten hun gewone gang gaan, zodat je woning er van buitenaf niet verlaten bij ligt.'
        enabled: true
    enabled: true
  -
    id: sm-bedienen
    type: cards
    overline: Bediening
    title: 'Drie manieren om te bedienen'
    text: 'Je gebruikt ze door elkaar, afhankelijk van waar je bent en wat je in handen hebt.'
    cards:
      -
        id: sm-b1
        type: card
        title: 'Smart Control'
        text:
          -
            type: paragraph
            content:
              -
                type: text
                text: 'Een fysieke bediening met twee knoppen waar je zelf een scenario aan koppelt.'
        features:
          -
            id: sm-b1f1
            type: feature
            label: 'Twee scenarioknoppen'
            enabled: true
          -
            id: sm-b1f2
            type: feature
            label: 'Werkt zonder smartphone'
            enabled: true
        enabled: true
        image: somfy/google_nest_-_blog_edited-(1).jpg
      -
        id: sm-b2
        type: card
        title: TaHoma-app
        text:
          -
            type: paragraph
            content:
              -
                type: text
                text: 'Je volledige installatie op je smartphone, zowel thuis als onderweg.'
        features:
          -
            id: sm-b2f1
            type: feature
            label: 'Alle toestellen in één scherm'
            enabled: true
          -
            id: sm-b2f2
            type: feature
            label: "Automatische scenario's instellen"
            enabled: true
        enabled: true
        image: somfy/ipad_tahoma2.0_hand_en-scaled.webp
      -
        id: sm-b3
        type: card
        title: Spraakassistent
        text:
          -
            type: paragraph
            content:
              -
                type: text
                text: 'Bedienen zonder dat je er iets voor in handen hoeft te nemen.'
        features:
          -
            id: sm-b3f1
            type: feature
            label: 'Alexa, Google Assistant of Siri'
            enabled: true
          -
            id: sm-b3f2
            type: feature
            label: 'Werkt via je slimme speaker'
            enabled: true
        enabled: true
        image: somfy/banner-tahoma-switch2-new1.jpg
    enabled: true
  -
    id: sm-energie
    type: text_image
    overline: Energie
    title: 'Rolluiken die op de thermometer reageren'
    text:
      -
        type: paragraph
        attrs:
          textAlign: left
        content:
          -
            type: text
            text: 'In de zomer gaan je rolluiken en jaloezieën automatisch dicht zodra het binnen boven de 25 graden gaat. Dat houdt het huis koel en beschermt je meubilair, je planten en je huisdieren tegen de felle zon.'
      -
        type: paragraph
        attrs:
          textAlign: left
        content:
          -
            type: text
            text: 'In de winter draait dat om. Dan gaan ze open om de zon als extra warmtebron binnen te laten, en dicht bij zonsondergang om die warmte binnen te houden.'
    media: image
    image: somfy/z-lf-6-woodlook-brown__75947.jpg
    features:
      -
        id: sm-e1
        type: feature
        label: 'Sluit automatisch bij hitte'
        enabled: true
      -
        id: sm-e2
        type: feature
        label: 'Opent voor gratis winterwarmte'
        enabled: true
      -
        id: sm-e3
        type: feature
        label: 'Stelt de airco uit'
        enabled: true
    background: true
    enabled: true
  -
    id: sm-specs
    type: technical_details
    overline: Technisch
    title: 'De cijfers'
    text: 'Wat er bij jou aangesloten kan worden, hangt af van de motoren die er al zitten. Dat bekijken we ter plaatse.'
    technical_details:
      -
        id: sm-s1
        key: Systeem
        value: 'Somfy TaHoma'
      -
        id: sm-s2
        key: Compatibiliteit
        value: 'Bijna 300 soorten huishoudelijke producten'
      -
        id: sm-s3
        key: Spraakassistenten
        value: 'Amazon Alexa, Google Assistant en Siri via Apple HomeKit'
      -
        id: sm-s4
        key: 'Aan te sluiten'
        value: 'Rolluiken, raamdecoratie, terraszonwering en garagepoorten'
      -
        id: sm-s5
        key: Bediening
        value: 'Smart Control, app of spraakassistent'
    enabled: false
  -
    id: sm-cta
    type: cta
    overline: Advies
    title: 'Werkt dit met wat je al hebt?'
    text: 'In veel gevallen wel, want TaHoma spreekt met bijna driehonderd producttypes. Laat ons weten welke motoren er bij jou zitten, dan zoeken we het uit.'
    image: somfy/woning-met-somfy-smart-home.webp
    align: left
    link:
      -
        id: sm-ctalink
        type: entry
        entry: f0ee3161-1534-4986-9ef1-a92fccfba619
        label: 'Neem contact op'
        new_tab: false
    enabled: true
updated_by: d308c19c-c205-4453-9862-1f62996a3734
updated_at: 1785701368
---
