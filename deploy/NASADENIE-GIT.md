# Nasadenie z Gitu na Hostcreators (bez SSH a Composera)

Hosting nasadzuje z Gitu do priečinkov `release_<čas>` a nemá SSH ani Composer. V Gite ale nie je `backend/vendor/` (knižnice), preto z `main` samotného backend nepobeží. Riešenie: GitHub po každom pushi do `main` zostaví **hotový strom aj s knižnicami** do vetvy **`deploy`** a hosting ťahá z nej.

## Ako to funguje
- Workflow `.github/workflows/deploy-vetva.yml` spustí `deploy/zostav-deploy-strom.sh`. Strom obsahuje zdroje bez testov, CI a pokynov pre vývoj, plus `backend/vendor/` a stub pre `/api`. Výsledok sa uloží (force push) do vetvy `deploy`. Vetvu `main` to nemení.
- `.env` a databáza **nie sú v kóde**. Patria do priečinka **`shared`** vedľa releaseov: `…/web/shared/.env` a `…/web/shared/var/nacenime.sqlite`. Pri každom novom release ostanú.
- Backend si `shared` nájde sám (`backend/src/Cesty.php`): ak kód beží z `…/release_X/backend`, číta `.env` zo `…/shared/.env` a databázu ukladá do `…/shared/var/`. Pri bežnom vývoji sa nič nemení.
- **Poistka:** ak je kód v `release_…` a priečinok `shared` neexistuje, API odpovie `503` a nič nezapíše. Databáza tak nikdy neskončí v nahrádzanom a verejnom priečinku.

## Čo treba urobiť (raz, v hosting paneli a na GitHube)
1. **GitHub → Actions → „Zostaviť vetvu deploy“ → Run workflow.** Vznikne vetva `deploy`.
2. **Hostcreators → GIT nasadenie:** zmeniť vetvu z `main` na **`deploy`**.
3. **Vytvoriť `…/web/shared/`** (vedľa `release_…`, `current`) a v ňom súbor `.env` podľa `backend/.env.example` (Google kľúč, SMTP, meno makléra, `NACENIME_DB_PATH=var/nacenime.sqlite`). Práva `.env` 600. Priečinok `shared` musí byť zapisovateľný pre PHP (databáza sa vytvorí v `shared/var/`).
4. **PHP 8.1** pre doménu a rozšírenia `pdo_sqlite`, `mbstring`, `curl`, `dom`, `gd`.
5. **Overiť:** `https://<doména>/api/health` vráti `{"stav":"ok"}`. Ak vráti `503`, chýba `shared`.
6. **Cron** (cestu k PHP overiť v paneli, napr. `/usr/bin/php8.1`; `current` ukazuje vždy na aktuálny release):
   ```
   * * * * *   /usr/bin/php8.1 /…/web/current/backend/bin/odosli-pdf.php
   0 3 * * *   /usr/bin/php8.1 /…/web/current/backend/bin/zaloha-db.php
   30 3 * * *  /usr/bin/php8.1 /…/web/current/backend/bin/gdpr.php retencia
   ```
7. ~~SSL certifikát~~ Hotové: `https://nacenime.sk` má platný certifikát Let's Encrypt (overené 13. 11. 2026, platný do 9. 2. 2027).

## Oznámenia o leadoch
Cron `odosli-pdf.php` posiela aj e-mail o každom novom leade (žiadosť o telefonát, PDF so súhlasom s kontaktovaním). Do `shared/.env` doplň `NACENIME_LEAD_EMAIL=adresa@…` (viac adries oddeľ čiarkou), voliteľne `NACENIME_LEAD_WEBHOOK=https://…` pre n8n alebo CRM. Bez týchto hodnôt sa leady iba ukladajú. Leady spred zavedenia oznámení sa spätne neoznamujú.

## Smerovanie `/api/*`
Do `design_handoff_nacenime/` sa v nasadenom strome pridávajú `api.php` (stub) a `.htaccess` (Apache), ktorý `/api/*` presmeruje na stub. **Koreň webu je `design_handoff_nacenime/`** (overené 13. 11. 2026: `https://nacenime.sk/` vracia tamojší `index.html`, `/design_handoff_nacenime/` vracia 404, a `backend/` leží mimo koreňa a nie je dostupný). Ak by sa koreň zmenil:
- koreň je celé repo: pridať do koreňového `.htaccess` `RewriteRule ^api(/|$) design_handoff_nacenime/api.php [L,QSA]`,
- server je čistý nginx a `.htaccess` ignoruje: požiadať podporu o `location /api/ { try_files $uri /design_handoff_nacenime/api.php?$query_string; }` (alebo ekvivalent).

## Čo nie je overené (v tomto prostredí sa to nedalo)
- Správanie Hostcreators GIT nasadenia: výber vetvy, či `shared` prežíva, či sa dajú spúšťať príkazy po nasadení.
- Skutočný koreň webu, Apache oproti nginx, a beh na PHP 8.1 (vyvíjalo sa na 8.3, závislosti 8.1 podporujú).
- Samotné workflow na GitHube (lokálne sa overil skript a test simulovaného rozloženia `backend/tests/nasadenie.php`).

## CRM (crm.nacenime.sk)
Nasadzovaný strom obsahuje priečinok `crm/` (stránka CRM, `api.php`, vlastný `.htaccess`, lokálny React a ikony). Na hostingu:
1. **Subdoména `crm.nacenime.sk`** s adresárom `web/current/crm` (Hostcreators → nacenime.sk → Subdomény → Pridať subdoménu), PHP 8.1, presmerovanie na HTTPS, SSL certifikát (Let's Encrypt).
2. Do `shared/.env` doplniť `NACENIME_CRM_URL=https://crm.nacenime.sk` a `NACENIME_CRM_ADMIN_EMAIL=<e-mail správcu>` (voliteľne `NACENIME_CRM_ADMIN_MENO`).
3. Prvé prihlásenie: na crm.nacenime.sk zadať e-mail správcu a kliknúť **Zabudnuté heslo**. Kým v CRM nie je nikto, účet sa pre tento e-mail založí a príde odkaz na nastavenie hesla. Maklérov potom pozýva správca v časti **Členovia**.
4. Pripomienky ďalších krokov posiela existujúci cron `odosli-pdf.php` (netreba novú úlohu).
