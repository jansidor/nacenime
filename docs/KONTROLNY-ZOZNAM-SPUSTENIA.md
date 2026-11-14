# Kontrolný zoznam spustenia nacenime.sk

Stav k 14. 11. 2026. Overené priamo na webe (nie len v repe).

## Hotové a overené
- ✅ **HTTPS** funguje, certifikát Let's Encrypt pre `nacenime.sk`, platný do 9. 2. 2027.
- ✅ **Koreň webu** je `design_handoff_nacenime/` a `backend/` leží mimo neho (nie je dostupný z internetu).
- ✅ **Nasadené z `main`:** `Nacenime.dc.html` a `cookies/cookies.js` na webe sú bajt po bajte rovnaké ako v `main` (PR #2, #3, #4).
- ✅ **Cookie lišta nasadená; pred súhlasom sa nič nesledovalo:** v nasadenom HTML nie je Clarity ani Google Tag Manager (načítajú sa až modulom po súhlase). Správanie lišty pokrýva test `design_handoff_nacenime/cookies/test.cjs` na tom istom kóde.
- ✅ `/.env` a `/.git/config` vracajú 403.
- ✅ Dizajn a texty sa nezmenili (zmrazený frontend; zmeny len cookie modul a hlavička stránky).
- ✅ **PR #5, #6, #7 zlúčené.** Vetva `deploy` (aj s knižnicami) sa zostavuje automaticky po každom pushi do `main`.
- ✅ **SEO základy na webe:** `lang="sk"`, titulok, popis, kanonická adresa `https://nacenime.sk/`, Open Graph a Twitter, JSON-LD `RealEstateAgent` (adresa, telefón), favicon, `og-image.png`, `robots.txt`, `sitemap.xml`.

## Na urobenie (poradie podľa dôležitosti)
| # | Čo | Kto |
|---|---|---|
| 1 | **Hostcreators:** vytvoriť `/web/shared/.env` (Google kľúč, meno makléra, SMTP), PHP 8.1 a rozšírenia, prepnúť Git nasadenie na vetvu **`deploy`**, cron (návod `deploy/NASADENIE-GIT.md`) | majiteľ hostingu |
| 2 | **Search Console:** doména `nacenime.sk`, DNS TXT `google-site-verification=…`, Verify, potom odoslať `https://nacenime.sk/sitemap.xml` | správca Google účtu a DNS |
| 3 | **Tag Manager** (`GTM-WHXQF3H7`): značka GA4 s `G-X0825SJMQ2`, potom Submit/Publish; v GA4 vypnúť interakcie s formulármi, uchovávanie 14 mesiacov | kto má prístup ku kontajneru |
| 4 | **E-mail:** SMTP účet pre `noreply@nacenime.sk` a DNS záznamy SPF, DKIM, DMARC | správca domény a e-mailu |
| 5 | **PR #8 (právne stránky):** doplniť IČO, e-mail, zápis v OR, overiť sídlo (inzeráty uvádzajú Podhora 55, JSON-LD Podhora 1330/48), právna kontrola, odstrániť `noindex` a rámček „Návrh“, zlúčiť | majiteľ firmy |
| 6 | Po zlúčení PR #8 prepojiť odkazy v pätičke `Nacenime.dc.html` (3× `href="#"` → `ochrana-osobnych-udajov.html`, `cookies.html`, `kontakt.html`) | majiteľ repa (zmrazený súbor) |
| 7 | **Podklady:** cenové dáta pre Tvrdošín, Turčianske Teplice, Námestovo; fotky; texty miest; potvrdiť `Dovalovo` | majiteľ obsahu |
| 8 | Rozhodnúť, ako sa leady z kalkulačky dostanú do **CRM** (chýba meno) | majiteľ |
| 9 | Zapnúť na `main` **Require review from Code Owners** | majiteľ repa (admin) |
| 10 | **Napojenie frontendu na API** a zapojenie mapy z `frontend/mapa/` (zmena zmrazeného prototypu) | po súhlase |

## Ručná kontrola cookie lišty (2 minúty)
1. Otvor `https://nacenime.sk` v **novom anonymnom okne**. Zobrazí sa lišta „Cookies“.
2. DevTools → **Network** → filter `google|clarity`: pred kliknutím **žiadne** požiadavky.
3. Klikni **Odmietnuť**: stále žiadne a lišta po obnovení zmizne.
4. V novom anonymnom okne klikni **Prijať všetko**: načíta sa `gtm.js?id=GTM-WHXQF3H7` a `clarity.ms`.
5. Odkaz **Cookies** v pätičke otvorí nastavenia, v ktorých sa dá súhlas odvolať.

## Známe riziká
- **`https://nacenime.sk/data/nacenime-cennik.json` je verejne stiahnuteľný** (leží v koreni webu). Cenník je však verejný aj v repe na GitHube. Do produkcie patrí cenník len na server.
- Web si pri načítaní sťahuje React a Babel z `unpkg.com` (prototypový runtime): závislosť od cudzieho CDN a pomalšie načítanie.
- Microsoft Clarity predtým bežal bez súhlasu a teraz len po analytickom súhlase. Dáta z neho teda ubudnú.
- Google Cloud projekt (kľúč) je dočasne na súkromnom účte, pred ostrým spustením prejsť na firemný.
