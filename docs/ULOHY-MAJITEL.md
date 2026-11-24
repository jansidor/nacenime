# Úlohy majiteľa na 25. 11. 2026 (čo treba spraviť ručne)

Stav: databáza CRM je overená na testovacom Supabase (migrácie 0001 až 0008, všetky testy, verejný kľúč nevolá funkcie CRM). Kód aplikácie je zlúčený. **Aplikácia ešte nie je nikde nasadená**, preto sa CRM nedá skúsiť v prehliadači. Všetko nižšie sú kroky, ktoré môže urobiť len človek s prístupmi. Kde sa dá, pomôže Claude v Chrome (kroky v administrácii) alebo bežný Claude (otázky). Heslá, kľúče a tajomstvá zadávaj sám priamo do polí, nikdy ich nevkladaj do chatu.

## 1. Doména rozia.sk (10 minút, rozhodni ráno)
Doména `rozia.sk` v DNS neexistuje (kontrola 24. 11.: verejný DNS vracia „neexistuje“, nemá ani menné servery).
- [ ] Zisti, či je `rozia.sk` zaregistrovaná (sk-nic.sk, alebo u registrátora, kde si registroval nacenime.sk).
- [ ] Ak nie je, zaregistruj ju. Ak je zaregistrovaná, nastav menné servery, inak sa neprekladá.
- Kým to nie je hotové, nič nebráni nasadeniu: web aj CRM pobežia na adrese `*.vercel.app`.

## 2. Vercel (30 minút)
- [ ] Vercel → Add New → Project → importuj repo `jansidor/rozia.sk`.
- [ ] Vetva: repo **nemá `main`**, kód je vo vetve `claude/eager-edison-tqydun`. Nastav ju ako Production Branch (alebo ju premenuj na `main` a nastav ako predvolenú, čistejšie, ale vyžaduje to zmenu v GitHube).
- [ ] Premenné prostredia (Production aj Preview), len názvy, hodnoty zadáš sám:
  - `NEXT_PUBLIC_SUPABASE_URL`, `NEXT_PUBLIC_SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY` (zo Supabase → Settings → API; service_role kľúč je tajný),
  - `NEXT_PUBLIC_SITE_URL` (adresa nasadenia; neskôr `https://rozia.sk`),
  - `CRON_SECRET` a `NACENIME_WEBHOOK_SECRET` (každé samostatne, `openssl rand -hex 32`; webhook tajomstvo si uchovaj, použiješ ho aj v nacenime),
  - `ROZIA_DEMO_DATA` nenastavuj (alebo `0`).
- [ ] Deploy. Otvor adresu `*.vercel.app`: verejný web musí fungovať.
- [ ] Neskôr: Domains → pridaj `rozia.sk` a nastav DNS záznamy, ktoré Vercel ukáže.

## 3. Supabase: prihlásenie (10 minút)
- [ ] Authentication → URL Configuration: Site URL = adresa nasadenia; do Redirect URLs pridaj adresu nasadenia.
- [ ] Authentication → Providers → Email: zapnuté, **verejná registrácia vypnutá** (účty zakladáš ty).
- [ ] Testovacie účty (admin, maklér, financie) už máš a majú rolu zo seedu. Ak si nepamätáš heslá, nastav nové v Authentication → Users.

## 4. Vyskúšať CRM v prehliadači (45 minút)
Otvor `<adresa nasadenia>/crm/` a prejdi:
- [ ] **Maklér:** prihlásenie → Nepridelené (zatiaľ prázdne, kým nepríde lead; viď bod 5) → Moje ocenenia.
- [ ] **Admin:** Na pridelenie → prideliť ocenenie maklérovi.
- [ ] **Maklér:** Moje ocenenia → detail → Vytvoriť nábor → upraviť údaje → pridať poznámku → Nedohodli sme sa (archív) → Obnoviť → Odovzdať kolegovi.
- [ ] **Financie:** Financovanie → nábor → kontakt klienta, zmena stavu, poznámka.
- Pri každej chybe si vyfoť obrazovku a pošli mi ju (alebo text chyby a adresu stránky).

## 5. Skúška príjmu leadov (15 minút, po bode 2)
Testovacím skriptom pošleš vymyslený lead (nepotrebuješ zapnúť skutočný webhook):
```bash
NACENIME_WEBHOOK_SECRET=<tajomstvo z Vercelu> node scripts/send-test-lead.mjs https://<adresa nasadenia>/api/inbound/nacenime/
```
- [ ] Odpoveď `HTTP 200` a `{"status":"processed"}`. V CRM sa objaví ocenenie na pridelenie (admin).
- [ ] Zmazanie testovacích údajov: SQL je v `docs/PROSTREDIA.md` v repe rozia (bod 6).

## 6. Skutoční zamestnanci
- [ ] V Authentication → Users založ účty maklérov, adminov a financií (e-mail, heslo).
- [ ] Rolu a pobočku nastav šablónou `supabase/seed/staff_template.sql` (vyplň mimo repa, v repe nech len šablónu). Pobočky: Ružomberok, Liptovský Mikuláš, Dolný Kubín, Martin.
- [ ] Neprítomnosť alebo odchod: dva riadky na konci šablóny.

## 7. Zapnutie webhooku z nacenime (až po bodoch 2, 4 a 5)
- [ ] Do `shared/.env` nacenime: `NACENIME_LEAD_WEBHOOK_SECRET=<rovnaké tajomstvo ako vo Verceli>` a `NACENIME_LEAD_WEBHOOK=https://<adresa nasadenia>/api/inbound/nacenime/` (**lomka na konci je povinná**).
- [ ] `php backend/bin/skontroluj-konfig.php` musí hlásiť „webhook je podpisovaný“.
- [ ] Zásady ochrany údajov sú už zverejnené, takže zapnutie je dovolené. E-mailové oznámenia o leadoch nechaj zapnuté popri webhooku, kým sa počty nezhodujú.
- [ ] Prvé dni porovnávaj počet leadov v nacenime a v CRM.

## 8. Rozhodnutia, ktoré čakajú na teba
- [ ] **Obchodné podmienky rozia** advokát ešte neschválil, na webe ostávajú ako návrh a neindexujú sa.
- [ ] **DIČ, IČ DPH a telefón** pre rozia.sk (ak ich chceš uvádzať).
- [ ] **Vrátený klient:** dnes prejde na makléra, ktorému je pridelené nové ocenenie. Chceš, aby sa radšej pridelilo pôvodnému maklérovi?
- [ ] **Produkčný Supabase** (samostatný projekt so zálohami, plán Pro) pred ostrými dátami. Teraz beží len testovací.
- [ ] **Duplikát CRM:** prístup len na čítanie pre kolegu (ak ešte nemá).

## 9. Čo robí kolega
Jeho zadanie je v `docs/PROMPT-1-KOLEGA.md` (alebo samostatný súbor `docs/balicky/ZADANIE-1-KOLEGA.md`): kontrola kódu, audit duplikátu CRM, mapovanie, import a skúška nasadenia.
