// Test mapy v skutočnom prehliadači (Playwright). Spustenie: node frontend/mapa/test.cjs
// Dlaždice OpenStreetMap sa nahrádzajú falošnými, aby test nezávisel od siete.
const http = require('http'), fs = require('fs'), path = require('path');
const { chromium } = require('playwright');

const korenSuborov = __dirname;
const typy = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css' };
const server = http.createServer((q, s) => {
  const f = path.join(korenSuborov, decodeURIComponent(new URL(q.url, 'http://x').pathname));
  if (!f.startsWith(korenSuborov) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { s.writeHead(404).end(); return; }
  s.writeHead(200, { 'Content-Type': typy[path.extname(f)] ?? 'application/octet-stream' }).end(fs.readFileSync(f));
});
// 1×1 px PNG roztiahnutý prehliadačom na dlaždicu
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

let zlyhania = 0;
const over = (nazov, ok, detail = '') => { if (ok) console.log('ok   ' + nazov); else { zlyhania++; console.log('FAIL ' + nazov + (detail ? '\n  ' + detail : '')); } };

(async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch();

  async function nova(ctxOpts = {}, { blokniLeaflet = false } = {}) {
    const ctx = await browser.newContext({ viewport: { width: 1000, height: 700 }, ...ctxOpts });
    const page = await ctx.newPage();
    const chyby = [], poziadavky = [];
    page.on('pageerror', (e) => chyby.push(String(e)));
    page.on('request', (r) => poziadavky.push(r.url()));
    await page.route('**/tile.openstreetmap.org/**', (r) => r.fulfill({ status: 200, contentType: 'image/png', body: PNG }));
    if (blokniLeaflet) await page.route('**/vendor/leaflet/leaflet.js', (r) => r.abort());
    return { ctx, page, chyby, poziadavky };
  }

  // 1. Základ: rozmery, tlačidlá, popisy, atribúcia
  {
    const { ctx, page, chyby, poziadavky } = await nova();
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const m = await page.evaluate(() => {
      const k = document.querySelector('#mapa'), cs = getComputedStyle(k);
      const b = [...document.querySelectorAll('.nm-zoom button')].map((x) => { const r = x.getBoundingClientRect(); return [x.getAttribute('aria-label'), Math.round(r.width), Math.round(r.height)]; });
      const at = document.querySelector('.leaflet-control-attribution');
      return { vyska: Math.round(k.getBoundingClientRect().height), radius: cs.borderTopLeftRadius, bg: cs.backgroundColor, tlacidla: b,
        pin: document.querySelector('.leaflet-marker-icon')?.getAttribute('aria-label'), pinTab: document.querySelector('.leaflet-marker-icon')?.tabIndex,
        atrib: at?.textContent, atribPx: at && getComputedStyle(at).fontSize, skupina: k.getAttribute('aria-label'), filter: getComputedStyle(document.querySelector('.leaflet-tile-pane')).filter };
    });
    over('výška 240 px, zaoblenie 24 px', m.vyska === 240 && m.radius === '24px', JSON.stringify(m));
    over('tlačidlá 44 × 44 s popismi', JSON.stringify(m.tlacidla) === JSON.stringify([['Priblížiť', 44, 44], ['Oddialiť', 44, 44]]), JSON.stringify(m.tlacidla));
    over('špendlík má popis a dá sa zaostriť', m.pin === 'Špendlík, dá sa posunúť ťahaním' && m.pinTab === 0, JSON.stringify([m.pin, m.pinTab]));
    over('skupina mapy má popis', m.skupina === 'Mapa s polohou adresy');
    over('atribúcia OpenStreetMap, písmo aspoň 16 px', m.atrib?.includes('OpenStreetMap') && parseFloat(m.atribPx) >= 16, JSON.stringify([m.atrib, m.atribPx]));
    over('sivý štýl mapy (grayscale)', m.filter.includes('grayscale'), m.filter);
    over('bez chýb v stránke', chyby.length === 0, chyby.join('; '));
    await ctx.close();
  }

  // 2. Leaflet sa nenačíta, kým sa mapa nepoužije
  {
    const { ctx, page, poziadavky } = await nova();
    await page.setContent('<div id="x"></div><p>bez mapy</p>');
    over('stránka bez mapy nesťahuje Leaflet', !poziadavky.some((u) => u.includes('leaflet')));
    await ctx.close();
  }

  // 3. Priblíženie a oddialenie
  {
    const { ctx, page } = await nova();
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const zoom = () => page.evaluate(() => window.__mapa.priblizenie());
    // Klik, potom počkať na dobehnutie animácie (Leaflet počas nej ďalší klik ignoruje).
    const klik = async (nazov) => { const pred = await zoom(); await page.getByRole('button', { name: nazov }).click(); await page.waitForFunction((p) => window.__mapa.priblizenie() !== p, pred, { timeout: 3000 }).catch(() => {}); await page.waitForTimeout(350); };
    const z0 = await zoom();
    await klik('Priblížiť');
    const z1 = await zoom();
    await klik('Oddialiť'); await klik('Oddialiť');
    const z2 = await zoom();
    over('tlačidlo + priblíži, − oddiali', z1 === z0 + 1 && z2 === z0 - 1, JSON.stringify([z0, z1, z2]));
    for (let i = 0; i < 20 && !(await page.getByRole('button', { name: 'Oddialiť' }).isDisabled()); i++) await klik('Oddialiť');
    over('na minimálnom priblížení je − vypnuté', (await page.getByRole('button', { name: 'Oddialiť' }).isDisabled()) && (await zoom()) === 5, String(await zoom()));
    for (let i = 0; i < 20 && !(await page.getByRole('button', { name: 'Priblížiť' }).isDisabled()); i++) await klik('Priblížiť');
    over('na maximálnom priblížení je + vypnuté', (await page.getByRole('button', { name: 'Priblížiť' }).isDisabled()) && (await zoom()) === 19, String(await zoom()));
    await ctx.close();
  }

  // 4. Ťahanie špendlíka myšou
  {
    const { ctx, page } = await nova();
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const pin = page.locator('.leaflet-marker-icon');
    const b = await pin.boundingBox();
    const x = b.x + b.width / 2, y = b.y + b.height / 2;
    await page.mouse.move(x, y); await page.mouse.down();
    await page.mouse.move(x + 40, y - 20, { steps: 8 }); await page.mouse.move(x + 80, y - 40, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(200);
    const z = await page.evaluate(() => window.__zmeny);
    over('ťahanie špendlíka zavolá naZmenu so súradnicami', z?.length === 1 && z[0].lng > 19.3048 && z[0].lat > 49.0816, JSON.stringify(z));
    over('súradnice majú najviac 6 desatinných miest', /^\d+(\.\d{1,6})?$/.test(String(z?.[0].lat)) && /^\d+(\.\d{1,6})?$/.test(String(z?.[0].lng)));
    over('stránka ukáže nové súradnice', (await page.locator('#sur').textContent()).includes(String(z[0].lat)));
    await ctx.close();
  }

  // 5. Klávesnica
  {
    const { ctx, page } = await nova();
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    await page.keyboard.press('Tab');
    await page.locator('.leaflet-marker-icon').focus();
    const lng0 = (await page.evaluate(() => window.__mapa.poloha())).lng;
    for (let i = 0; i < 3; i++) await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowUp');
    await page.waitForTimeout(500);
    const z = await page.evaluate(() => window.__zmeny);
    const p = await page.evaluate(() => window.__mapa.poloha());
    over('šípky posunú špendlík a oznámia zmenu raz', z?.length === 1 && p.lng > lng0 && p.lat > 49.0816, JSON.stringify([z, p]));
    const outline = await page.evaluate(() => getComputedStyle(document.querySelector('.leaflet-marker-icon')).outlineWidth);
    over('zaostrený špendlík má viditeľný obrys', parseFloat(outline) >= 3, outline);
    await ctx.close();
  }

  // 6. Mapa sa nenačíta: sivá plocha, nič nepadne
  {
    const { ctx, page, chyby } = await nova({}, { blokniLeaflet: true });
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const r = await page.evaluate(() => ({ ok: window.__mapa.ok, bg: getComputedStyle(document.querySelector('#mapa')).backgroundColor, h: document.querySelector('#mapa').getBoundingClientRect().height, sprava: document.querySelector('#sur').textContent }));
    over('pri zlyhaní ostane sivá plocha 240 px a stránka funguje', r.ok === false && r.bg === 'rgb(220, 217, 208)' && r.h === 240 && r.sprava.includes('nenačítala'), JSON.stringify(r));
    over('zlyhanie nevyhodí chybu stránky', chyby.length === 0, chyby.join('; '));
    await ctx.close();
  }

  // 7. Mobil: bez posúvania mapy jedným prstom, šírka nepretečie
  {
    const { ctx, page } = await nova({ viewport: { width: 390, height: 800 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const r = await page.evaluate(() => ({ pretok: document.documentElement.scrollWidth > innerWidth, sirka: Math.round(document.querySelector('#mapa').getBoundingClientRect().width) }));
    over('na mobile mapa nepretečie šírku', !r.pretok, JSON.stringify(r));
    await page.screenshot({ path: process.env.SNIMKA_MOBIL ?? '/dev/null' });
    await ctx.close();
  }

  // 8. Zmenšený pohyb
  {
    const { ctx, page } = await nova({ reducedMotion: 'reduce' });
    await page.goto(`${base}/demo.html`);
    await page.waitForFunction(() => window.__mapa);
    const an = await page.evaluate(() => document.querySelector('.leaflet-container').classList.contains('leaflet-zoom-anim'));
    over('pri prefers-reduced-motion nie je animácia priblíženia', an === false);
    await ctx.close();
  }

  await browser.close(); server.close();
  console.log(zlyhania ? `\n${zlyhania} zlyhaní` : '\nVšetko prešlo');
  process.exit(zlyhania ? 1 : 0);
})();
