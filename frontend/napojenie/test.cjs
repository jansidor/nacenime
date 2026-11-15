// Test napojenia prototypu na skutočné API (Playwright + php -S). Spustenie: node frontend/napojenie/test.cjs
// Backend beží s dočasnou databázou a bez Google kľúča (našepkávač = záloha). Odpovede Google pre našepkávač sa v teste simulujú.
const fs = require('fs'), os = require('os'), path = require('path');
const { spawn, execFileSync } = require('child_process');
const { chromium } = require('playwright');

const repo = path.resolve(__dirname, '..', '..');
const docroot = path.join(repo, 'design_handoff_nacenime');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nacenime-napojenie-'));
fs.writeFileSync(path.join(tmp, 'router.php'), `<?php
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($u, '/api/')) { $_SERVER['SCRIPT_NAME'] = '/index.php'; require ${JSON.stringify(repo + '/backend/public/index.php')}; return true; }
return false;
`);
const port = 20000 + Math.floor(Math.random() * 20000), base = `http://127.0.0.1:${port}`;
const srv = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', docroot, path.join(tmp, 'router.php')], { stdio: 'ignore',
  env: { PATH: process.env.PATH, NACENIME_DB_PATH: path.join(tmp, 't.sqlite'), NACENIME_RATE_LIMIT: '0', NACENIME_GOOGLE_API_KEY: '' } });

let fail = 0;
const ok = (n, c, d) => { if (c) console.log('ok   ' + n); else { fail++; console.log('FAIL ' + n + (d !== undefined ? '\n  ' + JSON.stringify(d) : '')); } };
const dbRows = (sql) => JSON.parse(execFileSync('php', ['-r', '$p=new PDO("sqlite:" . $argv[1]);echo json_encode($p->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));', path.join(tmp, 't.sqlite'), sql]).toString());
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  for (let i = 0; i < 50; i++) { try { if ((await fetch(base + '/api/health')).ok) break; } catch (e) {} await sleep(100); }
  const browser = await chromium.launch();
  async function nova(extra) {
    const ctx = await browser.newContext({ viewport: { width: 1100, height: 900 } });
    const page = await ctx.newPage(), st = { chyby: [], req: [], mapa: [], mapaPredPotvrdenim: 0 };
    page.on('pageerror', (e) => st.chyby.push(String(e)));
    page.on('request', (r) => { if (r.url().startsWith(base + '/api/')) st.req.push({ url: r.url().slice(base.length), metoda: r.method(), telo: r.postData() }); });
    page.on('request', (r) => { if (/unpkg\.com/.test(r.url())) st.unpkg = (st.unpkg || 0) + 1; });
    page.on('request', (r) => { if (/\/mapa\//.test(r.url())) st.mapa.push(r.url()); });
    await page.route(/^https:\/\/unpkg\.com\//, (r) => r.fulfill({ status: 404, body: '' })); // runtime je lokálny (vendor/), unpkg sa nesmie použiť
    await page.route(/^https?:\/\/(?!127\.0\.0\.1|unpkg\.com)/, (r) => r.fulfill({ status: 200, contentType: r.request().resourceType() === 'stylesheet' ? 'text/css' : 'text/javascript', body: '' }));
    if (extra) await extra(page);
    await page.goto(base + '/Nacenime.dc.html');
    await page.waitForSelector('h1', { timeout: 10000 });
    return { page, st };
  }
  const klik = (page, text) => page.getByRole('button', { name: text, exact: true }).click();
  async function kalkulacka(page, { typ = 'Byt', stav = 'Pôvodný', obec } = {}) {
    await klik(page, 'Len zisťujem'); await klik(page, typ);
    await page.fill('#vymera', '66,5'); await page.locator('form:has(#vymera) button[type=submit]').click();
    if (typ !== 'Pozemok') await klik(page, stav);
    await klik(page, 'Nie');
    await page.getByRole('link', { name: 'Preskočiť' }).click();
    if (obec) { await page.fill('#obec-kalk', obec); await page.locator('form:has(#obec-kalk) button[type=submit]').click(); }
    await page.locator('button:has-text("Zobraziť")').first().click();
  }

  // 1. Záloha (bez Google): dve polia, obec overí server, odhad zo servera, kontakty uložené
  {
    const { page, st } = await nova();
    await page.fill('#adresa-uvod', 'Bystrická cesta 12');
    await page.waitForSelector('#ulica-uvod', { timeout: 5000 });
    ok('záloha: po zlyhaní Google sa ukážu polia Ulica a Obec', true);
    ok('záloha: ulica sa prenesie z písaného textu', (await page.inputValue('#ulica-uvod')) === 'Bystrická cesta 12');
    await page.fill('#obec-uvod', 'Žilina'); await page.locator('#obec-uvod').blur();
    await page.waitForSelector('text=Zatiaľ oceňujeme len Liptov', { timeout: 5000 }).then(() => ok('záloha: obec mimo regiónu hlási server', true), () => ok('záloha: obec mimo regiónu hlási server', false));
    await page.fill('#obec-uvod', 'ruzomberok');
    await page.locator('form:has(#ulica-uvod) button[type=submit]').click();
    await kalkulacka(page);
    await page.waitForSelector('h1:has-text("až")', { timeout: 8000 });
    const h1 = await page.locator('h1').first().innerText();
    ok('výsledok: cena zo servera', /\d{3}\s?\d{3}\s+až\s+\d{3}\s?\d{3}\s€/.test(h1.replace(/ /g, ' ')), h1);
    const o = st.req.find((r) => r.url === '/api/odhad');
    const body = o && JSON.parse(o.telo);
    ok('odhad: telo zodpovedá zmluve', body && body.typ === 'Byt' && body.obec === 'ruzomberok' && body.vymera === '66.5' && body.stav === 'Pôvodný' && body.dovod === 'Len zisťujem' && body.balkon === 'Nie' && body.adresa && body.adresa.zo_zalohy === true, body);
    ok('odhad: bez lokálneho cenníka', !st.req.some((r) => /cennik/.test(r.url)) && !(await page.evaluate(() => performance.getEntriesByType('resource').some((e) => /nacenime-cennik/.test(e.name)))));
    // PDF: neplatný e-mail = veta zo servera
    await page.fill('#email-pdf', 'zle'); await klik(page, 'Poslať PDF');
    await page.waitForSelector('text=Zadajte e-mail.');
    await page.fill('#email-pdf', 'jano@example.sk'); await page.getByRole('checkbox').check(); await klik(page, 'Poslať PDF');
    await page.waitForSelector('text=Odhad vám pošleme na e-mail.');
    await page.fill('#telefon', '12'); await klik(page, 'Zavolajte mi'); await page.waitForSelector('text=Zadajte telefónne číslo.');
    await page.fill('#telefon', '0900 123 456'); await klik(page, 'Zavolajte mi'); await page.waitForSelector('text=Ďakujeme, ozveme sa vám.');
    const k = dbRows('select druh,email,telefon,suhlas,odhad_id from kontakty order by id');
    ok('kontakty sa uložili s odhad_id a súhlasom', k.length === 2 && k[0].druh === 'pdf' && k[0].email === 'jano@example.sk' && k[0].suhlas === 1 && k[0].odhad_id && k[1].druh === 'makler' && k[1].telefon, k);
    const hp = st.req.filter((r) => /kontakt/.test(r.url)).every((r) => JSON.parse(r.telo).web === '');
    ok('honeypot sa posiela prázdny', hp);
    ok('honeypot je skrytý pred čítačkou', await page.locator('input[name=web]').first().getAttribute('aria-hidden') === 'true' && await page.locator('input[name=web]').first().getAttribute('tabindex') === '-1');
    ok('knižnice sa načítali lokálne, nie z unpkg.com', !st.unpkg, st.unpkg);
    ok('bez chýb v konzole', st.chyby.length === 0, st.chyby);
    await page.context().close();
  }

  // 2. Obec mimo regiónu v zálohe -> Mimo regiónu, e-mail sa uloží
  {
    const { page } = await nova();
    await page.fill('#adresa-uvod', 'Hlavná 1'); await page.waitForSelector('#ulica-uvod');
    await page.fill('#obec-uvod', 'Žilina'); await page.locator('form:has(#ulica-uvod) button[type=submit]').click();
    await page.waitForSelector('#email-notify');
    await page.fill('#email-notify', 'x@example.sk'); await page.locator('form:has(#email-notify) button[type=submit]').click();
    await page.waitForSelector('text=Ďakujeme, dáme vám vedieť.');
    ok('mimo regiónu: e-mail uložený', dbRows("select count(*) n from kontakty where druh='mimo_regionu'")[0].n === 1);
    await page.context().close();
  }

  // 3. Našepkávač (Google simulovaný): návrhy, detail, potvrdenie, odhad so súradnicami
  {
    const { page, st } = await nova(async (p) => {
      await p.route('**/api/adresa?*', (r) => r.fulfill({ json: { navrhy: [{ id: 'ChIJabcdefghij', hlavny: 'Bystrická cesta 12', vedlajsi: 'Ružomberok, Slovensko' }] } }));
      await p.route('**/api/adresa/detail?*', (r) => r.fulfill({ json: { ulica: 'Bystrická cesta 12', psc: '034 01', obec: 'Ružomberok', adresa: 'x', lat: 49.0816, lng: 19.3048, v_regione: true } }));
    });
    await page.fill('#adresa-uvod', 'Bystr');
    await page.waitForSelector('[role=option]', { timeout: 5000 });
    ok('našepkávač: ukáže návrh od servera', (await page.locator('[role=option]').first().innerText()).includes('Bystrická cesta 12'));
    const a = st.req.find((r) => r.url.startsWith('/api/adresa?'));
    ok('našepkávač: token 8 až 64 znakov', a && /token=[A-Za-z0-9_-]{8,64}$/.test(a.url), a);
    st.mapaPredPotvrdenim = st.mapa.length;
    await page.locator('[role=option]').first().click();
    await page.waitForSelector('text=Je to správna adresa?');
    ok('potvrdenie: adresa s PSČ z detailu', (await page.locator('h1').first().innerText()).includes('034 01 Ružomberok'));
    const d = st.req.find((r) => r.url.startsWith('/api/adresa/detail'));
    ok('detail: rovnaký token ako pri hľadaní', d && a && d.url.match(/token=(\S+)/)[1] === a.url.match(/token=(\S+)/)[1]);
    ok('mapa: Leaflet sa stiahol až na obrazovke Potvrdenie adresy', st.mapa.length > 0 && st.mapaPredPotvrdenim === 0, [st.mapa.length, st.mapaPredPotvrdenim]);
    await page.waitForSelector('#mapa-potvrdenie.leaflet-container', { timeout: 8000 });
    ok('mapa: je na obrazovke Potvrdenie adresy, 240 px', (await page.locator('#mapa-potvrdenie').evaluate((e) => Math.round(e.getBoundingClientRect().height))) === 240 || (await page.locator('#mapa-potvrdenie').evaluate((e) => Math.round(e.getBoundingClientRect().height))) === 240);
    await page.locator('#mapa-potvrdenie .leaflet-marker-icon').focus();
    for (let i = 0; i < 3; i++) await page.keyboard.press('ArrowRight');
    await sleep(900);
    await klik(page, 'Áno, pokračovať');
    await kalkulacka(page, { typ: 'Dom', stav: 'Novostavba' });
    await page.waitForSelector('h1:has-text("až")', { timeout: 8000 });
    const o = JSON.parse(st.req.find((r) => r.url === '/api/odhad').telo);
    ok('odhad: súradnice po posune špendlíka mapy (lat z detailu, lng posunuté)', o.adresa && Math.abs(o.adresa.lat - 49.0816) < 0.001 && o.adresa.lng > 19.3048 && o.adresa.lng < 19.31 && o.adresa.psc === '034 01' && !o.adresa.zo_zalohy && o.obec === 'Ružomberok', o);
    ok('odhad: uložený so súradnicami', Math.abs(dbRows('select lat,lng from odhady order by id desc limit 1')[0].lng - 19.305124) < 0.0005);
    ok('mapa po odchode z obrazovky zmizne', (await page.locator('#mapa-potvrdenie').count()) === 0);
    await page.context().close();
  }

  // 4. Pozemok bez adresy: bez stavu, bez_adresy, obec z poslednej otázky
  {
    const { page, st } = await nova();
    await page.fill('#adresa-uvod', 'zzz'); await page.waitForSelector('#ulica-uvod');
    await page.getByRole('link', { name: 'Oceňujete pozemok? Pokračujte bez adresy.' }).click();
    await kalkulacka(page, { typ: 'Pozemok', obec: 'Liptovský Mikuláš' });
    await page.waitForSelector('h1', { timeout: 8000 }); await page.waitForTimeout(500);
    const o = JSON.parse(st.req.find((r) => r.url === '/api/odhad').telo);
    ok('pozemok: bez stavu, bez_adresy, obec z otázky', o.typ === 'Pozemok' && !('stav' in o) && o.adresa.bez_adresy === true && o.obec === 'Liptovský Mikuláš', o);
    await page.context().close();
  }

  // 5. Chyba servera na odhade -> text chyby a Skúsiť znova
  {
    const { page } = await nova(async (p) => { await p.route('**/api/odhad', (r) => r.fulfill({ status: 500, json: { chyba: 'x' } })); });
    await page.fill('#adresa-uvod', 'Hlavná 1'); await page.waitForSelector('#ulica-uvod');
    await page.fill('#obec-uvod', 'Martin'); await page.locator('form:has(#ulica-uvod) button[type=submit]').click();
    await kalkulacka(page);
    await page.waitForSelector('text=Skúsiť znova', { timeout: 8000 });
    ok('chyba servera: ukáže sa „Skúsiť znova“', true);
    await page.context().close();
  }

  // 6. Ukážkový režim (?screen=) nevolá server
  {
    const { page, st } = await nova();
    await page.goto(base + '/Nacenime.dc.html?screen=' + encodeURIComponent('Výsledok'));
    await page.waitForSelector('h1:has-text("až")', { timeout: 8000 });
    ok('ukážkový režim: výsledok bez volania /api', !st.req.some((r) => r.url.startsWith('/api/odhad')));
    await page.goto(base + '/Nacenime.dc.html?screen=' + encodeURIComponent('Potvrdenie adresy'));
    await page.waitForSelector('#mapa-potvrdenie.leaflet-container', { timeout: 8000 }).then(() => ok('ukážkový režim: mapa na Potvrdení adresy', true), () => ok('ukážkový režim: mapa na Potvrdení adresy', false));
    await page.context().close();
  }

  await browser.close();
  srv.kill(); fs.rmSync(tmp, { recursive: true, force: true });
  console.log(fail ? `\n${fail} zlyhaní` : '\nvšetko prešlo');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); srv.kill(); process.exit(1); });
