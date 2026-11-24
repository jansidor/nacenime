# Kontrolný zoznam spustenia nacenime.sk

Stav k 16. 11. 2026. Overené priamo na webe, ak nie je uvedené inak.

## Hotové a overené
- ✅ **HTTPS**, `www` presmeruje na `https://nacenime.sk/`, bezpečnostné hlavičky (HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy).
- ✅ **Kalkulačka napojená na API:** našepkávač adries cez Google, mapa (OpenStreetMap), odhad zo servera, formuláre „Odhad v PDF“ a „Zavolajte mi“ (s nepovinným menom). Pozemok preskakuje stav aj balkón, odkaz pre pozemok bez adresy je vždy na úvode.
- ✅ **Bez cudzích serverov pred súhlasom:** písmo, React aj mapa idú z `nacenime.sk`. GTM a Clarity až po súhlase s cookies. Udalosti pre GA4 (`odhad`, `pdf`, `telefonat`) len po súhlase, bez osobných údajov (`docs/MERANIE.md`).
- ✅ **Telefóny (iPhone, Android):** bez pretečenia, plochy na ťuknutie aspoň 44 px, bez prilepeného hoveru, správna klávesnica, manifest pre Android.
- ✅ **Právne stránky** zverejnené, v sitemap, s menom v zásadách.
- ✅ **SEO a Search Console:** doména overená, sitemap odoslaná, stránky požiadané o indexovanie.
- ✅ **CRM** beží na `crm.nacenime.sk` (správca prihlásený). API CRM len na tejto doméne, limit pokusov, GDPR mazanie aj v CRM, opravy pre telefóny.
- ✅ **PDF** s kontaktom na makléra, správnym obchodným menom; odpoveď klienta ide na `info@nacenime.sk`.
- ✅ **Cenník a `reference/` skryté** (404), databáza s indexmi.
- ✅ **CI** spúšťa PHP testy aj testy v prehliadači (kalkulačka, cookies, mapa, CRM).

## Na urobenie
| # | Čo | Kto |
|---|---|---|
| 1 | **SMTP** do `shared/.env` a **cron** `odosli-pdf.php` každú minútu (+ záloha a retencia denne); skúška PDF na vlastný e-mail | správca hostingu |
| 2 | **Oznámenia o leadoch:** `NACENIME_LEAD_EMAIL`; voliteľne SMS/Telegram cez n8n (`NACENIME_LEAD_WEBHOOK`); podpis webhooku `NACENIME_LEAD_WEBHOOK_SECRET` (`docs/WEBHOOK.md`) | správca hostingu |
| 3 | **Monitoring** (UptimeRobot): `/api/health` a `/api/health/fronta` (beží cron, e-maily neviaznu). Návod v `deploy/NASADENIE-GIT.md` | správca |
| 4 | **Zmazať skúšobné záznamy** (leady `0900 000 000`, `test@example.com`, testovacie odhady) cez `backend/bin/gdpr.php zmaz` | správca hostingu |
| 5 | **CRM:** pozvať maklérov | majiteľ |
| 6 | **GTM a GA4** podľa `docs/MERANIE.md`, kľúčové udalosti `pdf` a `telefonat` | kto má prístup ku GTM |
| 7 | **E-mail DNS:** SPF, DKIM, DMARC | správca domény |
| 8 | **Záloha mimo servera** (napr. n8n do Google Drive) | správca |
| 9 | **Cenové dáta** pre Tvrdošín, Turčianske Teplice a Námestovo (`docs/CENNIK-PODKLADY.md`) | majiteľ obsahu |
| 10 | Fotky a texty miest (Martin, Dolný Kubín, Námestovo) | majiteľ obsahu |
| 11 | Google Cloud: rozpočet a upozornenie, presun projektu na firemný účet | správca Google účtu |

## Ručná kontrola po nastavení (5 minút)
1. `https://nacenime.sk/` na mobile: adresa, návrh, mapa, 5 otázok, výsledok s rozpätím.
2. „Odhad v PDF“ na vlastný e-mail: PDF príde, nie je v spame, odpoveď ide na `info@nacenime.sk`.
3. „Zavolajte mi“ s vymysleným číslom a menom: príde oznámenie, lead je v CRM s menom. Potom ho zmaž.
4. `https://nacenime.sk/api/health/fronta` vráti `200` a `"stav": "ok"`.
5. Cookie lišta v anonymnom okne: pred kliknutím žiadne požiadavky na `google|clarity`.

## Známe riziká
- Prototypový runtime (React z `vendor/`, šablóny v prehliadači). Stránka má po kompresii približne 150 kB, ďalšie zrýchlenie by vyžadovalo nahradiť runtime.
- Verejné dlaždice OpenStreetMap sú bez záruky dostupnosti. Pri raste návštevnosti treba platenú službu (jedna konštanta v `mapa/mapa.js`).
- Cenník je verejný v repe na GitHube (nie tajomstvo, len na webe skrytý).
