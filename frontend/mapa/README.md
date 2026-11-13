# Mapa (Leaflet + OpenStreetMap)

Mapa pre obrazovku **Potvrdenie adresy**, bez Google Maps, bez API kľúča a bez poplatkov. Je to samostatný modul: nezasahuje do zmrazeného frontendu (`design_handoff_nacenime/`). Zapojí sa až po súhlase.

## Použitie
```html
<div id="mapa"></div>
<script type="module">
  import { vytvorMapu } from '/frontend/mapa/mapa.js';
  const m = await vytvorMapu(document.getElementById('mapa'), {
    lat: 49.0816, lng: 19.3048,                       // z /api/adresa/detail
    naZmenu: ({ lat, lng }) => { /* uložiť nové súradnice */ },
    naChybu: () => { /* voliteľné: mapa sa nenačítala */ },
  });
  // m.ok === false => ostala sivá plocha, adresa a tlačidlá fungujú ďalej
</script>
```
`m.nastav(lat, lng)`, `m.poloha()`, `m.priblizenie()`, `m.zrus()`. Leaflet sa stiahne **až pri prvom volaní** `vytvorMapu` (mapa sa nemá načítať na úvode).

## Podľa zadania (`design_handoff_nacenime/README.md`)
- Výška 240 px, zaoblenie 24 px (zadanie na jednom mieste uvádza 20 px a na inom 24 px, použil som 24 px z popisu obrazovky), sivá plocha `#dcd9d0` kým sa nenačíta, aj keď sa nenačíta.
- Špendlík `#272361` so svetlým stredom, **ťahateľný**, po posune sa zavolá `naZmenu` so súradnicami (6 desatinných miest).
- Vpravo dole tlačidlá + a − 44 × 44 px, biele s rámom. Popisy pre čítačky: „Priblížiť“, „Oddialiť“, „Špendlík, dá sa posunúť ťahaním“.
- Sivý štýl mapy, žiadne iné ovládacie prvky.
- Zdroj mapy „© OpenStreetMap“ je povinný, písmo 16 px (nič nie je pod 16 px).

## Prístupnosť a správanie
- Špendlík sa dá zaostriť a **posúvať šípkami** (Shift = väčší krok), zmena sa oznámi po krátkej pauze.
- Zaostrenie: obrys 3 px `#272361`, odstup 2 px.
- Pri `prefers-reduced-motion` sa vypínajú animácie.
- Kolieskom myši sa nepriblíži (neblokuje posúvanie stránky). Na dotykových zariadeniach jeden prst posúva stránku, mapou sa hýbe špendlíkom a tlačidlami.

## Testy
```bash
node frontend/mapa/test.cjs        # potrebuje Playwright
```
Overuje rozmery, popisy, ťahanie, klávesnicu, priblíženie, zlyhanie načítania, mobil a zmenšený pohyb. Dlaždice OpenStreetMap sa v teste nahrádzajú falošnými.
**Nie je overené so skutočnými dlaždicami OSM** (v mojom prostredí je sieť obmedzená). Pozri aj ukážku `demo.html`.

## Dlaždice OpenStreetMap: pravidlá
Verejný server `tile.openstreetmap.org` je zadarmo, ale bez záruky dostupnosti a s pravidlami používania (uvedenie zdroja, rozumný objem, identifikovateľný odkazujúci web). Pri malom vyťažení stačí. Ak by návštevnosť rástla, treba prejsť na platenú alebo vlastnú službu dlaždíc (zmena jedinej konštanty `DLAZDICE` v `mapa.js`).

## Súradnice a Google
Mapa potrebuje súradnice. Tie dáva `GET /api/adresa/detail` (Google Places), takže bez kľúča pre Places sa pri záložných poliach „Ulica a Obec“ mapa nezobrazí, ako je v zadaní. Našepkávač stále potrebuje Google kľúč, mapa už nie.

## Licencia
Leaflet 1.9.4 (BSD-2-Clause) je v `vendor/leaflet/` aj s licenciou.
