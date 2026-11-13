# Kontrolný zoznam spustenia nacenime.sk

Stav k 13. 11. 2026. Overené priamo na webe (nie len v repe).

## Hotové a overené
- ✅ **HTTPS** funguje, certifikát Let's Encrypt pre `nacenime.sk`, platný do 9. 2. 2027.
- ✅ **Koreň webu** je `design_handoff_nacenime/` a `backend/` leží mimo neho (nie je dostupný z internetu).
- ✅ **Nasadené z `main`:** `Nacenime.dc.html` a `cookies/cookies.js` na webe sú bajt po bajte rovnaké ako v `main` (PR #2, #3, #4).
- ✅ **Cookie lišta nasadená; pred súhlasom sa nič nesledovalo:** v nasadenom HTML nie je Clarity ani Google Tag Manager (načítajú sa až modulom po súhlase). Správanie lišty pokrýva test `design_handoff_nacenime/cookies/test.cjs` na tom istom kóde.
- ✅ `/.env` a `/.git/config` vracajú 403.
- ✅ Dizajn a texty sa nezmenili (zmrazený frontend, jediná zmena je cookie modul).

## Na urobenie (poradie podľa dôležitosti)
| # | Čo | Kto |
|---|---|---|
| 1 | Zlúčiť **PR #5** (oprava občasného `database is locked`) | majiteľ repa |
| 2 | Rozhodnúť o **PR #6** (nasadenie backendu): spustiť workflow, prepnúť hosting na vetvu `deploy`, vytvoriť `shared/.env`, cron, potvrdiť PHP 8.1 | majiteľ hostingu |
| 3 | **Tag Manager** (`GTM-WHXQF3H7`): značka GA4 s `G-X0825SJMQ2`, potom Submit/Publish | kto má prístup ku kontajneru |
| 4 | **Search Console:** DNS TXT záznam `google-site-verification=…` pre `nacenime.sk`, potom Verify | správca DNS |
| 5 | **Google kľúč** (Places API (New)) do `shared/.env` ako `NACENIME_GOOGLE_API_KEY` | majiteľ hostingu (kľúč nikdy cez chat) |
| 6 | **SMTP** údaje do `shared/.env` (odosielanie PDF) a meno makléra | majiteľ hostingu |
| 7 | **Podklady:** cenové dáta pre Tvrdošín, Turčianske Teplice, Námestovo; fotky; texty miest; potvrdiť `Dovalovo` | majiteľ obsahu |
| 8 | **Právne texty** (zásady ochrany osobných údajov, cookies, kontakt) a právna kontrola textu cookie lišty | majiteľ firmy |
| 9 | Rozhodnúť, ako sa leady z kalkulačky dostanú do **CRM** (chýba meno) | majiteľ |
| 10 | Zapnúť na `main` **Require review from Code Owners** | majiteľ repa |
| 11 | **Napojenie frontendu na API** (zmena zmrazeného frontendu, potrebuje súhlas) a zapojenie mapy z `frontend/mapa/` | po súhlase |

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
