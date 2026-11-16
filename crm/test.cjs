// Test CRM v prehliadači (Playwright + php -S). Spustenie: node crm/test.cjs
// Dočasná databáza so správcom, maklérom a 3 leadmi z kalkulačky. Nič nejde do siete.
const fs = require('fs'), os = require('os'), path = require('path');
const { spawn, execFileSync } = require('child_process');
const { chromium, devices } = require('playwright');

const repo = path.resolve(__dirname, '..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nacenime-crm-'));
const db = path.join(tmp, 'crm.sqlite');
const HESLO = 'testovacie-heslo-123';

fs.writeFileSync(path.join(tmp, 'router.php'), `<?php
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($u, '/api/')) { require ${JSON.stringify(repo + '/backend/public/index.php')}; return true; }
if (str_starts_with($u, '/vendor/')) { $f = ${JSON.stringify(repo + '/design_handoff_nacenime')} . $u; if (is_file($f)) { header('Content-Type: text/javascript'); readfile($f); return true; } }
return false;
`);
fs.writeFileSync(path.join(tmp, 'seed.php'), `<?php
require ${JSON.stringify(repo + '/backend/vendor/autoload.php')};
foreach (['Config','Odhad','Validator','Storage','Adresy','Api','CrmStorage','Crm'] as $c) require ${JSON.stringify(repo + '/backend/src/')} . "$c.php";
use Nacenime\\{Api,Config,Odhad,Storage,CrmStorage};
$st = new Storage($argv[1]);
$api = new Api(Odhad::zo_suboru(${JSON.stringify(repo + '/design_handoff_nacenime/data/nacenime-cennik.json')}), new Config(['NACENIME_RATE_LIMIT' => '0']), $st);
$crm = new CrmStorage($st);
$crm->nastavHeslo($crm->vytvorPouzivatela('Ján Sidor', 'admin@test.sk', 'Správca'), $argv[2]);
$crm->nastavHeslo($crm->vytvorPouzivatela('Mária Šimková', 'maria@test.sk', 'Maklér'), $argv[2]);
foreach ([['Byt', 'Ružomberok', '0905 111 222', null, 'Jana Nováková'], ['Dom', 'Martin', null, 'klient@example.sk', null], ['Pozemok', 'Dolný Kubín', '0911 555 666', null, null]] as [$t, $o, $tel, $em, $meno]) {
  $b = ['typ' => $t, 'obec' => $o, 'vymera' => $t === 'Pozemok' ? 800 : 72, 'adresa' => ['ulica' => 'Hlavná 1', 'psc' => '034 01']];
  if ($t !== 'Pozemok') $b['stav'] = 'Pôvodný';
  $id = $api->handle('POST', '/api/odhad', $b)[1]['odhad_id'];
  $tel ? $api->handle('POST', '/api/kontakt/makler', ['telefon' => $tel, 'meno' => $meno, 'odhad_id' => $id]) : $api->handle('POST', '/api/kontakt/pdf', ['email' => $em, 'suhlas' => true, 'odhad_id' => $id]);
}
`);
execFileSync('php', [path.join(tmp, 'seed.php'), db, HESLO], { env: { PATH: process.env.PATH, NACENIME_RATE_LIMIT: '0' } });

const port = 20000 + Math.floor(Math.random() * 20000), base = `http://127.0.0.1:${port}`;
const srv = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', path.join(repo, 'crm'), path.join(tmp, 'router.php')], { stdio: 'ignore',
  env: { PATH: process.env.PATH, NACENIME_DB_PATH: db, NACENIME_RATE_LIMIT: '0', NACENIME_CRM_COOKIE_SECURE: '0', NACENIME_CRM_URL: base } });

let fail = 0;
const ok = (n, c, d) => { if (c) console.log('ok   ' + n); else { fail++; console.log('FAIL ' + n + (d !== undefined ? '\n  ' + JSON.stringify(d) : '')); } };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  for (let i = 0; i < 50; i++) { try { if ((await fetch(base + '/api/health')).ok) break; } catch (e) {} await sleep(100); }
  const browser = await chromium.launch();
  async function prihlas(ctxOpts, email) {
    const ctx = await browser.newContext(ctxOpts); const page = await ctx.newPage(); const chyby = [];
    page.on('pageerror', (e) => chyby.push(String(e)));
    await page.route(/^https?:\/\/(?!127\.0\.0\.1)/, (r) => r.fulfill({ status: 200, body: '' }));
    await page.goto(base + '/CRM.dc.html'); await page.waitForSelector('input[type=email]', { timeout: 10000 });
    await page.fill('input[type=email]', email); await page.fill('input[type=password]', HESLO);
    await page.getByRole('button', { name: 'Prihlásiť' }).click();
    await page.waitForSelector('text=Odhlásiť', { timeout: 8000 });
    return { ctx, page, chyby };
  }

  // 1. Zlé heslo
  {
    const ctx = await browser.newContext(); const page = await ctx.newPage();
    await page.goto(base + '/CRM.dc.html'); await page.waitForSelector('input[type=email]');
    await page.fill('input[type=email]', 'admin@test.sk'); await page.fill('input[type=password]', 'zle-heslo-12345');
    await page.getByRole('button', { name: 'Prihlásiť' }).click();
    await page.waitForSelector('text=Prihlásenie sa nepodarilo', { timeout: 5000 }).then(() => ok('zlé heslo: chybová hláška', true), () => ok('zlé heslo: chybová hláška', false));
    await ctx.close();
  }

  // 2. Správca na počítači: všetky leady, meno z kalkulačky, detail, poznámka, odhlásenie
  {
    const { ctx, page, chyby } = await prihlas({ viewport: { width: 1280, height: 900 } }, 'admin@test.sk');
    ok('správca vidí 3 leady', (await page.getByText('3 leady').count()) === 1);
    ok('meno z kalkulačky je na karte', (await page.getByText('Jana Nováková').count()) >= 1);
    await page.getByText('Jana Nováková').first().click();
    await page.waitForSelector('#dn', { timeout: 5000 });
    await page.fill('#dn', 'Volať po 17:00'); await page.getByRole('button', { name: /Pridať poznámku|Uložiť poznámku|Pridať/ }).first().click();
    await page.waitForSelector('text=Volať po 17:00', { timeout: 5000 }).then(() => ok('poznámka sa uloží', true), () => ok('poznámka sa uloží', false));
    ok('bez chýb v konzole', chyby.length === 0, chyby);
    await ctx.close();
  }

  // 3. Maklér nevidí nepridelené leady
  {
    const { ctx, page } = await prihlas({ viewport: { width: 1280, height: 900 } }, 'maria@test.sk');
    ok('maklér bez pridelených leadov nevidí žiadny', (await page.getByText('0905 111 222').count()) === 0 && (await page.getByText('Jana Nováková').count()) === 0);
    ok('maklér nevidí záložku Členovia', (await page.getByRole('button', { name: 'Členovia' }).count()) === 0);
    await ctx.close();
  }

  // 4. Telefóny: bez pretečenia, polia 16 px (iPhone nepriblíži), údaje pod sebou, žiadny :hover mimo myši
  for (const meno of ['iPhone SE', 'Pixel 7']) {
    const { ctx, page } = await prihlas(devices[meno], 'admin@test.sk');
    const zle = [];
    for (const krok of ['Leady', 'Tabuľka', 'Prehľad', 'Členovia']) {
      const t = page.getByRole('button', { name: krok }); if (!(await t.count())) continue;
      await t.first().click(); await page.waitForTimeout(300);
      const r = await page.evaluate(() => ({ pretok: document.documentElement.scrollWidth - innerWidth,
        pismo: [...document.querySelectorAll('input,select,textarea')].filter((e) => e.offsetParent && parseFloat(getComputedStyle(e).fontSize) < 16).length }));
      if (r.pretok > 0 || r.pismo) zle.push(krok + ' ' + JSON.stringify(r));
    }
    ok(meno + ': žiadne pretečenie, polia aspoň 16 px', zle.length === 0, zle);
    await page.getByRole('button', { name: 'Leady' }).first().click(); await page.getByRole('button', { name: 'Kanban' }).first().click().catch(() => {});
    await page.getByText('Jana Nováková').first().click(); await page.waitForSelector('#dn');
    const r = await page.evaluate(() => { const dl = document.querySelector('.nm-dl'); const dt = dl.querySelector('dt').getBoundingClientRect(), dd = dl.querySelector('dd').getBoundingClientRect();
      const tel = [...dl.querySelectorAll('a[href^="tel:"]')][0]; return { podSebou: dd.top >= dt.bottom - 1, telJedenRiadok: tel ? tel.getBoundingClientRect().height < 60 : true,
        mailtoPomlcka: !!document.querySelector('a[href="mailto:–"]') }; });
    ok(meno + ': detail leadu čitateľný (údaje pod sebou, telefón na riadku, bez odkazu mailto:–)', r.podSebou && r.telJedenRiadok && !r.mailtoPomlcka, r);
    const hover = await page.evaluate(() => { const z = []; for (const sh of document.styleSheets) { let rr; try { rr = sh.cssRules; } catch (e) { continue; } for (const x of rr) if (x.selectorText && /:hover/.test(x.selectorText)) z.push(x.selectorText); } return z; });
    ok(meno + ': :hover len pre zariadenia s myšou', hover.length === 0, hover);
    await ctx.close();
  }

  await browser.close();
  srv.kill(); fs.rmSync(tmp, { recursive: true, force: true });
  console.log(fail ? `\n${fail} zlyhaní` : '\nvšetko prešlo');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); srv.kill(); process.exit(1); });
