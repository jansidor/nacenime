// Test cookie súhlasu na skutočnej stránke (Playwright). Spustenie: node design_handoff_nacenime/cookies/test.cjs
// Externé požiadavky (Google, Clarity, fonty) sa zachytávajú a nahrádzajú prázdnou odpoveďou: nič nejde do siete.
const http = require('http'), fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const os = require('os');

// Prototyp (support.js) si pri štarte sťahuje React, ReactDOM a Babel z unpkg.com. V teste ich podávame z lokálnej kópie
// (stiahnutej cez npm do dočasného priečinka), aby stránka bežala bez siete. Cestu možno zadať cez UNPKG_DIR.
function pripravUnpkg() {
  const dir = process.env.UNPKG_DIR || path.join(os.tmpdir(), 'nacenime-unpkg-fixtures');
  const subory = { 'react@18.3.1/umd/react.production.min.js': ['react@18.3.1', 'package/umd/react.production.min.js'],
    'react-dom@18.3.1/umd/react-dom.production.min.js': ['react-dom@18.3.1', 'package/umd/react-dom.production.min.js'],
    '@babel/standalone@7.29.0/babel.min.js': ['@babel/standalone@7.29.0', 'package/babel.min.js'] };
  fs.mkdirSync(dir, { recursive: true });
  for (const [url, [balik, vnutri]] of Object.entries(subory)) {
    const cil = path.join(dir, url);
    if (fs.existsSync(cil)) continue;
    const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nm-pack-'));
    execSync(`npm pack ${balik} --silent`, { cwd: tmp, stdio: 'pipe' });
    const tgz = fs.readdirSync(tmp).find((f) => f.endsWith('.tgz'));
    execSync(`tar -xzf ${tgz} ${vnutri}`, { cwd: tmp });
    fs.mkdirSync(path.dirname(cil), { recursive: true });
    fs.copyFileSync(path.join(tmp, vnutri), cil);
  }
  return dir;
}
const UNPKG = pripravUnpkg();

const koren = path.resolve(__dirname, '..'); // design_handoff_nacenime/
const typy = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.jpg': 'image/jpeg' };
const server = http.createServer((q, s) => {
  let f = path.join(koren, decodeURIComponent(new URL(q.url, 'http://x').pathname));
  if (!f.startsWith(koren)) { s.writeHead(403).end(); return; }
  if (fs.existsSync(f) && fs.statSync(f).isDirectory()) f = path.join(f, 'index.html');
  if (!fs.existsSync(f)) { s.writeHead(404).end(); return; }
  s.writeHead(200, { 'Content-Type': typy[path.extname(f)] ?? 'application/octet-stream' }).end(fs.readFileSync(f));
});

let zlyhania = 0;
const over = (nazov, ok, detail = '') => { if (ok) console.log('ok   ' + nazov); else { zlyhania++; console.log('FAIL ' + nazov + (detail ? '\n  ' + detail : '')); } };
const SLEDOVANIE = /googletagmanager\.com|google-analytics\.com|clarity\.ms/;

(async () => {
  await new Promise((r) => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch();

  async function nova(opts = {}) {
    const ctx = await browser.newContext({ viewport: { width: 1100, height: 800 }, ...opts });
    const page = await ctx.newPage();
    const st = { sledovanie: [], chyby: [], navigacie: 0 };
    page.on('pageerror', (e) => st.chyby.push(String(e)));
    page.on('request', (r) => { if (SLEDOVANIE.test(r.url())) st.sledovanie.push(r.url()); });
    page.on('framenavigated', (f) => { if (f === page.mainFrame()) st.navigacie++; });
    await page.route(/^https:\/\/unpkg\.com\//, (r) => { const f = path.join(UNPKG, new URL(r.request().url()).pathname); return fs.existsSync(f) ? r.fulfill({ status: 200, contentType: 'text/javascript', body: fs.readFileSync(f) }) : r.fulfill({ status: 404, body: '' }); });
    await page.route(/^https?:\/\/(?!127\.0\.0\.1|unpkg\.com)/, (r) => r.fulfill({ status: 200, contentType: r.request().resourceType() === 'stylesheet' ? 'text/css' : 'text/javascript', body: '' }));
    return { ctx, page, st };
  }
  const otvor = async (page, url = '/Nacenime.dc.html') => { await page.goto(base + url); await page.waitForSelector('main, h1', { timeout: 8000 }).catch(() => {}); await page.waitForTimeout(300); };
  const cookieNm = (ctx) => ctx.cookies().then((c) => c.find((x) => x.name === 'nm_cookies'));
  const lista = (page) => page.locator('.nm-ck');

  // 1. Prvá návšteva: lišta je vidieť, nič sa nenačíta
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    over('prvá návšteva: lišta je zobrazená', await lista(page).isVisible());
    over('prvá návšteva: žiadne sledovanie v sieti', st.sledovanie.length === 0, st.sledovanie.join(', '));
    over('prvá návšteva: nie je dataLayer ani clarity', await page.evaluate(() => !window.dataLayer && !window.clarity));
    over('prvá návšteva: nie je uložená žiadna voľba', !(await cookieNm(ctx)));
    const m = await page.evaluate(() => {
      const d = document.querySelector('.nm-ck'); const bs = [...d.querySelectorAll('.nm-ck-tl button')].map((b) => { const r = b.getBoundingClientRect(); return [b.textContent, Math.round(r.width), Math.round(r.height), getComputedStyle(b).backgroundColor, getComputedStyle(b).color, getComputedStyle(b).borderTopColor]; });
      const mali = [...d.querySelectorAll('*')].filter((e) => e.childNodes.length && [...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim()) && parseFloat(getComputedStyle(e).fontSize) < 16).length;
      return { rola: d.getAttribute('role'), meno: d.getAttribute('aria-labelledby'), bs, mali, shadow: getComputedStyle(d).boxShadow, radius: getComputedStyle(d).borderTopLeftRadius };
    });
    over('lišta je dialóg s názvom', m.rola === 'dialog' && m.meno === 'nm-ck-nadpis');
    over('Prijať a Odmietnuť sú rovnocenné (rovnaká veľkosť a farby)', m.bs.length === 2 && JSON.stringify(m.bs[0].slice(1)) === JSON.stringify(m.bs[1].slice(1)), JSON.stringify(m.bs));
    over('tlačidlá majú výšku aspoň 60 px', m.bs.every((b) => b[2] >= 60), JSON.stringify(m.bs));
    over('žiadny text nie je pod 16 px', m.mali === 0, String(m.mali));
    over('bez tieňov (dizajn) a zaoblenie 12 px', m.shadow === 'none' && m.radius === '12px', JSON.stringify([m.shadow, m.radius]));
    over('bez chýb v stránke', st.chyby.length === 0, st.chyby.join('; '));
    await ctx.close();
  }

  // 2. Odmietnuť: nič sa nenačíta, voľba sa pamätá
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    await page.getByRole('button', { name: 'Odmietnuť' }).click();
    over('po odmietnutí lišta zmizne', (await lista(page).count()) === 0);
    const c = await cookieNm(ctx);
    over('voľba sa uloží ako cookie s analytikou 0', c && /^1\.0\.\d+$/.test(decodeURIComponent(c.value)), JSON.stringify(c));
    over('cookie platí asi 6 mesiacov a je SameSite=Lax', c && Math.round((c.expires - Date.now() / 1000) / 86400) >= 180 && c.sameSite === 'Lax', JSON.stringify(c));
    await otvor(page);
    over('po novej návšteve lišta nie je', (await lista(page).count()) === 0);
    over('po odmietnutí nikdy žiadne sledovanie', st.sledovanie.length === 0, st.sledovanie.join(', '));
    await ctx.close();
  }

  // 3. Prijať: Consent Mode v2 a potom GTM a Clarity
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    await page.getByRole('button', { name: 'Prijať všetko' }).click();
    await page.waitForTimeout(500);
    over('po prijatí lišta zmizne', (await lista(page).count()) === 0);
    over('načíta sa GTM-WHXQF3H7', st.sledovanie.some((u) => u.includes('googletagmanager.com/gtm.js?id=GTM-WHXQF3H7')), st.sledovanie.join(', '));
    over('načíta sa Clarity zbymfki8xh', st.sledovanie.some((u) => u.includes('clarity.ms/tag/zbymfki8xh')), st.sledovanie.join(', '));
    const dl = await page.evaluate(() => (window.dataLayer || []).map((x) => (typeof x.length === 'number' && !Array.isArray(x) ? Array.from(x) : x)));
    over('Consent Mode: najprv default zamietnuté', dl[0]?.[0] === 'consent' && dl[0]?.[1] === 'default' && ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'].every((k) => dl[0][2][k] === 'denied'), JSON.stringify(dl[0]));
    over('Consent Mode: potom povolená len analytika', dl[1]?.[1] === 'update' && dl[1][2].analytics_storage === 'granted' && Object.keys(dl[1][2]).length === 1, JSON.stringify(dl[1]));
    over('GTM štart až po consent', dl[2]?.event === 'gtm.js', JSON.stringify(dl[2]));
    const c = await cookieNm(ctx);
    over('voľba sa uloží s analytikou 1', c && /^1\.1\.\d+$/.test(decodeURIComponent(c.value)));
    // ďalšia návšteva: bez lišty, sledovanie sa spustí
    const pred = st.sledovanie.length;
    await otvor(page);
    over('ďalšia návšteva: bez lišty a sledovanie beží', (await lista(page).count()) === 0 && st.sledovanie.length > pred);
    over('bez chýb v stránke', st.chyby.length === 0, st.chyby.join('; '));
    await ctx.close();
  }

  // 4. Nastavenia: analytika je predvolene vypnutá, nevyhnutné nejde vypnúť
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    await page.getByRole('button', { name: 'Nastavenia' }).click();
    const ana = page.getByLabel(/Analytické/), nev = page.getByLabel(/Nevyhnutné/);
    over('v nastaveniach je analytika predvolene vypnutá', !(await ana.isChecked()));
    over('nevyhnutné je zapnuté a nedá sa vypnúť', (await nev.isChecked()) && (await nev.isDisabled()));
    await page.getByRole('button', { name: 'Uložiť výber' }).click();
    over('uloženie bez analytiky: žiadne sledovanie', st.sledovanie.length === 0 && /^1\.0\./.test(decodeURIComponent((await cookieNm(ctx)).value)), st.sledovanie.join(', '));
    await ctx.close();
    const b = await nova();
    await otvor(b.page);
    await b.page.getByRole('button', { name: 'Nastavenia' }).click();
    await b.page.getByLabel(/Analytické/).check();
    await b.page.getByRole('button', { name: 'Uložiť výber' }).click();
    await b.page.waitForTimeout(400);
    over('uloženie so zapnutou analytikou spustí sledovanie', b.st.sledovanie.some((u) => u.includes('GTM-WHXQF3H7')));
    await b.ctx.close();
  }

  // 5. Esc nie je rozhodnutie
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    await page.getByRole('button', { name: 'Nastavenia' }).click();
    await page.keyboard.press('Escape');
    over('Esc v nastaveniach vráti hlavnú lištu a nič neuloží', (await lista(page).getByRole('heading', { name: 'Cookies', exact: true }).isVisible()) && !(await cookieNm(ctx)) && st.sledovanie.length === 0);
    await ctx.close();
  }

  // 6. Odvolanie cez odkaz „Cookies“ v pätičke
  {
    const { ctx, page, st } = await nova();
    await otvor(page);
    await page.getByRole('button', { name: 'Prijať všetko' }).click();
    await page.waitForTimeout(400);
    await ctx.addCookies([{ name: '_ga', value: 'GA1.1.1', url: base }, { name: '_ga_ABC123', value: 'x', url: base }, { name: '_clck', value: 'x', url: base }]);
    await page.locator('footer a', { hasText: /^Cookies$/ }).click();
    over('odkaz Cookies v pätičke otvorí nastavenia s analytikou zapnutou', (await lista(page).isVisible()) && (await page.getByLabel(/Analytické/).isChecked()));
    const pred = st.sledovanie.length, navPred = st.navigacie;
    await page.getByLabel(/Analytické/).uncheck();
    await page.getByRole('button', { name: 'Uložiť výber' }).click();
    await page.waitForLoadState('load');
    await page.waitForTimeout(800);
    const cs = await ctx.cookies();
    over('odvolanie: voľba je 0', /^1\.0\./.test(decodeURIComponent(cs.find((c) => c.name === 'nm_cookies')?.value ?? '')));
    over('odvolanie: analytické cookies sú zmazané', !cs.some((c) => /^(_ga|_ga_.*|_clck)$/.test(c.name)), cs.map((c) => c.name).join(','));
    over('odvolanie: stránka sa načítala znova', st.navigacie > navPred);
    over('odvolanie: po načítaní sa už nič nesťahuje', st.sledovanie.length === pred, `${pred} -> ${st.sledovanie.length}`);
    over('odvolanie: lišta sa znova nepýta', (await lista(page).count()) === 0);
    await ctx.close();
  }

  // 7. Vypršaná a stará verzia voľby: lišta sa ukáže znova
  {
    for (const [nazov, hodnota] of [['po 7 mesiacoch', `1.1.${Math.floor(Date.now() / 1000) - 60 * 60 * 24 * 215}`], ['iná verzia', `9.1.${Math.floor(Date.now() / 1000)}`], ['poškodená hodnota', 'nezmysel']]) {
      const { ctx, page, st } = await nova();
      await ctx.addCookies([{ name: 'nm_cookies', value: encodeURIComponent(hodnota), url: base }]);
      await otvor(page);
      over(`cookie ${nazov}: lišta sa ukáže a nič sa nenačíta`, (await lista(page).isVisible()) && st.sledovanie.length === 0, st.sledovanie.join(', '));
      await ctx.close();
    }
  }

  // 8. Vo vnorenom okne (náhľady dizajnu) nič
  {
    const { ctx, page, st } = await nova();
    await page.route(base + '/okno.html', (r) => r.fulfill({ contentType: 'text/html', body: `<iframe src="${base}/Nacenime.dc.html" width="400" height="600"></iframe>` }));
    await page.goto(base + '/okno.html');
    await page.waitForTimeout(1500);
    const vnutri = page.frames().find((f) => f !== page.mainFrame());
    over('v iframe nie je lišta ani sledovanie', vnutri && (await vnutri.locator('.nm-ck').count()) === 0 && st.sledovanie.length === 0);
    await ctx.close();
  }

  // 9. Klávesnica a zaostrenie
  {
    const { ctx, page } = await nova();
    await otvor(page);
    const fokus = await page.evaluate(() => document.activeElement?.className);
    over('po zobrazení je zaostrená lišta (čítačky ju oznámia)', (fokus || '').split(' ').includes('nm-ck'), fokus);
    await page.keyboard.press('Tab');
    over('Tab ide na prvé tlačidlo lišty', (await page.evaluate(() => document.activeElement?.textContent)) === 'Prijať všetko');
    const obrys = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return [s.outlineWidth, s.outlineOffset]; });
    over('zaostrenie má obrys 3 px s odstupom 2 px', obrys[0] === '3px' && obrys[1] === '2px', JSON.stringify(obrys));
    await ctx.close();
  }

  // 9b. Na počítači lišta nezakrýva pole s adresou
  {
    const { ctx, page } = await nova({ viewport: { width: 1280, height: 720 } });
    await otvor(page);
    const r = await page.evaluate(() => {
      const l = document.querySelector('.nm-ck').getBoundingClientRect();
      const pole = [...document.querySelectorAll('input')].find((i) => i.getBoundingClientRect().width > 100)?.getBoundingClientRect();
      return { lista: [Math.round(l.top), Math.round(l.height)], poleSpodok: pole ? Math.round(pole.bottom) : null };
    });
    over('počítač 1280×720: lišta je nízka a nezakrýva pole adresy', r.poleSpodok !== null && r.poleSpodok < r.lista[0] && r.lista[1] <= 200, JSON.stringify(r));
    await ctx.close();
  }

  // 10. Mobil: snímky a bez pretečenia
  {
    const { ctx, page } = await nova({ viewport: { width: 390, height: 800 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await otvor(page);
    const r = await page.evaluate(() => ({ pretok: document.documentElement.scrollWidth > innerWidth, vyska: Math.round(document.querySelector('.nm-ck').getBoundingClientRect().height) }));
    over('mobil: lišta nepretečie šírku a zaberá menej ako 40 % výšky', !r.pretok && r.vyska < 0.4 * 800, JSON.stringify(r));
    if (process.env.SNIMKA_DIR) {
      await page.screenshot({ path: path.join(process.env.SNIMKA_DIR, 'cookies-mobil.png') });
      await page.getByRole('button', { name: 'Nastavenia' }).click();
      await page.screenshot({ path: path.join(process.env.SNIMKA_DIR, 'cookies-mobil-nastavenia.png') });
    }
    await ctx.close();
    const d = await nova({ viewport: { width: 1100, height: 700 }, deviceScaleFactor: 2 });
    await otvor(d.page);
    if (process.env.SNIMKA_DIR) await d.page.screenshot({ path: path.join(process.env.SNIMKA_DIR, 'cookies-desktop.png') });
    await d.ctx.close();
  }

  await browser.close(); server.close();
  console.log(zlyhania ? `\n${zlyhania} zlyhaní` : '\nVšetko prešlo');
  process.exit(zlyhania ? 1 : 0);
})();
