# Kontrolný zoznam spustenia nacenime.sk

Stav k 15. 11. 2026. Overené priamo na webe, ak nie je uvedené inak.

## Hotové a overené
- ✅ **HTTPS** (Let's Encrypt), `www` presmeruje na `https://nacenime.sk/`, aplikácia na `/`.
- ✅ **Backend beží zo `shared/`** (`/api/health` = ok), meno makléra nastavené.
- ✅ **Kalkulačka napojená na API:** našepkávač adries cez Google vracia návrhy, odhad zo servera, formuláre „Odhad v PDF“ a „Zavolajte mi“ ukladajú kontakt (200).
- ✅ **Mapa** (Leaflet + OpenStreetMap) na obrazovke Potvrdenie adresy. Skutočné dlaždice z môjho prostredia neoverené, treba pozrieť v prehliadači.
- ✅ **React a Babel lokálne** z `vendor/`, nič sa nesťahuje z `unpkg.com`. Hosting komprimuje (brotli) a cachuje statické súbory.
- ✅ **Cenník a `reference/` skryté** (`/data/nacenime-cennik.json` = 404).
- ✅ **Cookie lišta:** GTM a Clarity až po súhlase.
- ✅ **Právne stránky** (ochrana osobných údajov, cookies, kontakt) zverejnené, bez `noindex`, v sitemap.
- ✅ **SEO a Search Console:** doména overená, sitemap odoslaná, 4 stránky požiadané o indexovanie.
- ✅ **Prístupnosť** (mobil aj počítač): písmo nikde pod 16 px, kontrast aspoň 4,5 : 1, bez vodorovného pretečenia, viditeľný fokus.
- ✅ **CRM a oznámenia o leadoch** v kóde (PR #14, #15).

## Na urobenie
| # | Čo | Kto |
|---|---|---|
| 1 | **SMTP** do `shared/.env` a **cron** (`odosli-pdf.php` každú minútu, záloha a retencia denne); skúška PDF na vlastný e-mail | správca hostingu |
| 2 | **Oznámenia o leadoch:** `NACENIME_LEAD_EMAIL` do `shared/.env` | správca hostingu |
| 3 | **CRM:** subdoména `crm.nacenime.sk` → `web/current/crm` s SSL, `NACENIME_CRM_URL` a `NACENIME_CRM_ADMIN_EMAIL`, prvé prihlásenie cez „Zabudnuté heslo“ | správca hostingu |
| 4 | **Zmazať skúšobné záznamy** v databáze (testovacie odhady a kontakty `test@example.com`, `0900 000 000`) | správca hostingu |
| 5 | **Tag Manager** (`GTM-WHXQF3H7`): GA4 `G-X0825SJMQ2`, Publish; v GA4 vypnúť interakcie s formulármi, uchovávanie 14 mesiacov | kto má prístup ku GTM |
| 6 | **E-mail DNS:** SPF, DKIM, DMARC pre `nacenime.sk` | správca domény |
| 7 | **Cenové dáta** pre Tvrdošín, Turčianske Teplice a Námestovo (návod `docs/CENNIK-PODKLADY.md`) | majiteľ obsahu |
| 8 | Fotky a texty miest (Martin, Dolný Kubín, Námestovo) | majiteľ obsahu |
| 9 | Google Cloud: rozpočet a upozornenie v Billing, presun projektu na firemný účet | správca Google účtu |
| 10 | GitHub: na `main` vyžadovať pull request | majiteľ repa |

## Ručná kontrola po nastavení (5 minút)
1. `https://nacenime.sk/` na mobile: napíš adresu, vyber návrh, mapa ukáže okolie so špendlíkom, prejdi 5 otázok, výsledok ukáže rozpätie.
2. „Odhad v PDF“ na vlastný e-mail: PDF príde a nie je v spame.
3. „Zavolajte mi“ s vymysleným číslom: príde oznámenie na `NACENIME_LEAD_EMAIL` a lead je v CRM. Potom ho zmaž.
4. Cookie lišta v anonymnom okne: pred kliknutím žiadne požiadavky na `google|clarity`, po „Prijať všetko“ sa načíta `gtm.js` a GA4 v Realtime ukáže návštevu.

## Známe riziká
- Stránka má po kompresii približne 150 kB (React, runtime, písmo). Babel z `vendor/` sa nesťahuje, runtime ho potrebuje len pre `.jsx` súbory, ktoré kalkulačka nepoužíva. React sa sťahuje súbežne so `support.js` (`preload`). Ďalšie zrýchlenie by vyžadovalo nahradiť prototypový runtime.
- Verejné dlaždice OpenStreetMap sú bez záruky dostupnosti. Pri raste návštevnosti treba platenú službu (jedna konštanta v `mapa/mapa.js`).
- Cenník je verejný v repe na GitHube (nie tajomstvo, len na webe skrytý).
