// Mapa so ťahateľným špendlíkom (Leaflet + OpenStreetMap), bez API kľúča a bez poplatkov.
// Použitie:
//   import { vytvorMapu } from './mapa.js';
//   const m = await vytvorMapu(document.querySelector('#mapa'), { lat: 49.0816, lng: 19.3048, naZmenu: ({lat, lng}) => ... });
// Leaflet sa načíta lokálne až pri prvom použití (mapa sa nemá načítať na úvode).
// Ak sa mapa nenačíta, ostane sivá plocha a adresa s tlačidlami fungujú ďalej (m.ok === false).

const KORENOVY = new URL('.', import.meta.url);
const TEXTY = {
  mapa: 'Mapa s polohou adresy',
  pin: 'Špendlík, dá sa posunúť ťahaním',
  plus: 'Priblížiť',
  minus: 'Oddialiť',
};
const DLAZDICE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATRIBUCIA = '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap</a>';
const PIN_SVG = '<svg width="40" height="52" viewBox="0 0 40 52" aria-hidden="true" focusable="false">'
  + '<path d="M20 1C9.5 1 1 9.4 1 19.8 1 33.3 20 51 20 51s19-17.7 19-31.2C39 9.4 30.5 1 20 1z" fill="#272361"/>'
  + '<circle cx="20" cy="19.5" r="7" fill="#f6f4ef"/></svg>';
const IKONA_PLUS = '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M10 3v14M3 10h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/></svg>';
const IKONA_MINUS = '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M3 10h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/></svg>';

let leafletSlub;
function pridajStyl(href) {
  if (document.querySelector(`link[data-nm="${href}"]`)) return;
  const l = document.createElement('link');
  l.rel = 'stylesheet';
  l.href = href;
  l.dataset.nm = href;
  document.head.append(l);
}
function nacitajLeaflet() {
  if (globalThis.L?.map) return Promise.resolve(globalThis.L);
  leafletSlub ??= new Promise((ok, chyba) => {
    pridajStyl(new URL('mapa.css', KORENOVY).href);
    pridajStyl(new URL('vendor/leaflet/leaflet.css', KORENOVY).href);
    const s = document.createElement('script');
    s.src = new URL('vendor/leaflet/leaflet.js', KORENOVY).href;
    s.onload = () => (globalThis.L?.map ? ok(globalThis.L) : chyba(new Error('Leaflet sa nenačítal')));
    s.onerror = () => { leafletSlub = undefined; chyba(new Error('Leaflet sa nenačítal')); };
    document.head.append(s);
  });
  return leafletSlub;
}
const zaokruhli = (x) => Math.round(x * 1e6) / 1e6;

/**
 * @param {HTMLElement} kontajner prázdny prvok; dostane výšku 240 px a sivé pozadie
 * @param {{lat:number, lng:number, zoom?:number, naZmenu?:(p:{lat:number,lng:number})=>void, naChybu?:(e:Error)=>void, texty?:Partial<typeof TEXTY>}} moznosti
 */
export async function vytvorMapu(kontajner, moznosti) {
  const t = { ...TEXTY, ...moznosti.texty };
  kontajner.classList.add('nm-mapa');
  kontajner.setAttribute('role', 'group');
  kontajner.setAttribute('aria-label', t.mapa);
  pridajStyl(new URL('mapa.css', KORENOVY).href); // sivá plocha hneď, aj keď Leaflet zlyhá

  let L;
  try {
    L = await nacitajLeaflet();
  } catch (e) {
    moznosti.naChybu?.(e);
    return { ok: false, nastav() {}, poloha: () => ({ lat: moznosti.lat, lng: moznosti.lng }), priblizenie: () => null, zrus() {} };
  }

  const bezPohybu = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const dotyk = L.Browser.mobile;
  const mapa = L.map(kontajner, {
    center: [moznosti.lat, moznosti.lng],
    zoom: moznosti.zoom ?? 17,
    zoomControl: false,
    attributionControl: false,
    scrollWheelZoom: false,          // koleso nemá zachytávať posúvanie stránky
    dragging: !dotyk,                // na dotykových zariadeniach jeden prst posúva stránku, nie mapu
    keyboard: false,
    zoomAnimation: !bezPohybu,
    fadeAnimation: !bezPohybu,
    markerZoomAnimation: !bezPohybu,
    maxZoom: 19,
    minZoom: 5,
  });
  L.tileLayer(DLAZDICE, { maxZoom: 19, attribution: ATRIBUCIA, crossOrigin: true }).addTo(mapa);
  L.control.attribution({ position: 'bottomleft', prefix: false }).addTo(mapa);

  const pin = L.marker([moznosti.lat, moznosti.lng], {
    draggable: true,
    keyboard: false,
    icon: L.divIcon({ className: 'nm-pin', html: PIN_SVG, iconSize: [40, 52], iconAnchor: [20, 51] }),
  }).addTo(mapa);
  const el = pin.getElement();
  el.tabIndex = 0;
  el.setAttribute('role', 'button');
  el.setAttribute('aria-label', t.pin);

  const oznam = () => {
    const p = pin.getLatLng();
    moznosti.naZmenu?.({ lat: zaokruhli(p.lat), lng: zaokruhli(p.lng) });
  };
  pin.on('dragend', oznam);

  // Klávesnica: šípky posúvajú špendlík (Shift = väčší krok), oznámi sa po krátkej pauze
  let casovac;
  el.addEventListener('keydown', (e) => {
    const krok = e.shiftKey ? 50 : 10;
    const posun = { ArrowLeft: [-krok, 0], ArrowRight: [krok, 0], ArrowUp: [0, -krok], ArrowDown: [0, krok] }[e.key];
    if (!posun) return;
    e.preventDefault();
    e.stopPropagation();
    const bod = mapa.latLngToContainerPoint(pin.getLatLng()).add(posun);
    pin.setLatLng(mapa.containerPointToLatLng(bod));
    if (!mapa.getBounds().pad(-0.1).contains(pin.getLatLng())) mapa.panTo(pin.getLatLng(), { animate: !bezPohybu });
    clearTimeout(casovac);
    casovac = setTimeout(oznam, 250);
  });

  // Tlačidlá priblíženia
  const zoom = document.createElement('div');
  zoom.className = 'nm-zoom';
  const tlacidlo = (popis, ikona, zmena) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', popis);
    b.innerHTML = ikona;
    b.addEventListener('click', () => (zmena > 0 ? mapa.zoomIn() : mapa.zoomOut()));
    return b;
  };
  const plus = tlacidlo(t.plus, IKONA_PLUS, 1);
  const minus = tlacidlo(t.minus, IKONA_MINUS, -1);
  zoom.append(plus, minus);
  kontajner.append(zoom);
  L.DomEvent.disableClickPropagation(zoom);
  const stav = () => { plus.disabled = mapa.getZoom() >= mapa.getMaxZoom(); minus.disabled = mapa.getZoom() <= mapa.getMinZoom(); };
  mapa.on('zoomend', stav);
  stav();

  return {
    ok: true,
    /** Presunie špendlík (napr. po opravenej adrese) bez volania naZmenu. */
    nastav(lat, lng) { pin.setLatLng([lat, lng]); mapa.setView([lat, lng], mapa.getZoom(), { animate: false }); },
    poloha() { const p = pin.getLatLng(); return { lat: zaokruhli(p.lat), lng: zaokruhli(p.lng) }; },
    priblizenie: () => mapa.getZoom(),
    zrus() { clearTimeout(casovac); mapa.remove(); zoom.remove(); },
  };
}
