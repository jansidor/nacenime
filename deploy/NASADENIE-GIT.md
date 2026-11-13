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
7. **SSL certifikát** pre doménu (dnes HTTPS nefunguje).

## Smerovanie `/api/*`
Do `design_handoff_nacenime/` sa v nasadenom strome pridávajú `api.php` (stub) a `.htaccess` (Apache), ktorý `/api/*` presmeruje na stub. Predpokladá sa, že koreň webu je `design_handoff_nacenime/` (tam je `index.html`). Ak je koreň webu iný:
- koreň je celé repo: pridať do koreňového `.htaccess` `RewriteRule ^api(/|$) design_handoff_nacenime/api.php [L,QSA]`,
- server je čistý nginx a `.htaccess` ignoruje: požiadať podporu o `location /api/ { try_files $uri /design_handoff_nacenime/api.php?$query_string; }` (alebo ekvivalent).

## Čo nie je overené (v tomto prostredí sa to nedalo)
- Správanie Hostcreators GIT nasadenia: výber vetvy, či `shared` prežíva, či sa dajú spúšťať príkazy po nasadení.
- Skutočný koreň webu, Apache oproti nginx, a beh na PHP 8.1 (vyvíjalo sa na 8.3, závislosti 8.1 podporujú).
- Samotné workflow na GitHube (lokálne sa overil skript a test simulovaného rozloženia `backend/tests/nasadenie.php`).
