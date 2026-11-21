# Pracovný prompt 2: Supabase, prostredie a základ aplikácie (nižšie modely)

Pre majiteľa projektu. Skopíruj do Claude Code. Funguje aj na menších modeloch, preto je rozdelený na **malé samostatné úlohy**. Zadávaj ich **po jednej**, po každej počkaj na výsledok a skontroluj ho. Prompt neobsahuje tajomstvá ani mená.

---

## ROLA A CIEĽ
Pomáhaš stavať **CRM v projekte rozia.sk** (Next.js 16, TypeScript strict, Tailwind 4, Supabase). Tvoja časť: prostredie, databáza, prihlásenie, role a základné obrazovky. Komplikované prepojenia (napojenie na duplikát CRM NajBývania, webhook z nacenime.sk) robí iný človek podľa iného promptu. Do jeho častí nezasahuj.

## KDE SA PRACUJE
- Repo `jansidor/rozia.sk`. Plán celej integrácie je v repe **`jansidor/nacenime`**, súbor `docs/PLAN-INTEGRACIE.md`. **Prečítaj ho celý, než začneš.**
- Pracuj vo vetve (nie na `main`). Každá úloha = jeden malý pull request.
- Pred pull requestom vždy: `npm run typecheck`, `npm test`, `npm run build`.

## ZLATÉ PRAVIDLÁ
1. **Tajomstvá** (kľúče Supabase, heslá, `.env`) nikdy do repa, commitov, PR ani chatu. Do `.env.local` (necommitovať) a do Vercelu.
2. **Žiadne reálne osobné údaje** v kóde, testoch ani seed dátach. Len vymyslené.
3. **Migrácie len pridávajú.** Nikdy `drop`, `rename` ani nemeň existujúce tabuľky z `0001_public_web.sql`. Nové tabuľky = nový súbor `0002_...sql`, `0003_...sql` atď.
4. **Každá nová tabuľka má zapnuté RLS** a politiky. Bez politiky = nikto nič nevidí (to je správne).
5. **Najprv staging** (samostatný Supabase projekt). Produkciu sa nedotýkaj bez povolenia majiteľa.
6. Dizajn podľa existujúcich komponentov v `components/` a tokenov v `app/globals.css`. Text nie menší ako 16 px.
7. Nevymýšľaj si. Ak niečo nevieš alebo plán nehovorí, **opýtaj sa** namiesto hádania.
8. Po každej úlohe napíš: čo si urobil, čo si spustil a výsledok, čo si **nespustil**.

## ROZDELENIE MIGRÁCIÍ
**Ty píšeš migrácie** pre: organizácie, pobočky, profily, role, nábory, pridelenie, dokumenty (šablóny a vyplnené kópie), financovanie, audit. **Migrácie pre vstupné tabuľky `inbound_events`, `import_batches`, `sync_runs` píše kolega**, nepíš ich. Názvy tabuliek sa dohadujú v `docs/ADR-*.md` v repe rozia **pred** použitím.

## ÚLOHY (zadávaj po jednej, v tomto poradí)

### 1. Prostredie (F0)
- **1.1** Pridaj do rozia CI (GitHub Actions): `typecheck`, `test`, `build`, `check:contrast`. (I-003)
- **1.2** Pridaj priečinok `docs/` s `README` a šablónou `ADR-000-sablona.md` (záznam rozhodnutia: kontext, rozhodnutie, dôsledky). (I-008)
- **1.3** Napíš krok za krokom návod pre majiteľa, ako založiť **staging Supabase projekt** (Frankfurt) a Vercel preview prostredie, aké premenné nastaviť (len názvy, žiadne hodnoty). Súbor `docs/PROSTREDIA.md`. (I-006, I-007)
- **1.4** Rozšír `.env.example` o nové premenné (len názvy a popis, bez hodnôt).

### 2. Dátový model (F3, F4)
Pred písaním SQL napíš a daj schváliť `docs/ADR-001-datovy-model.md` s ER diagramom (Mermaid) pre:
- `organizations`, `branches` (4 pobočky: Liptovský Mikuláš, Ružomberok, Dolný Kubín, Martin/Vrútky), `branch_districts` (pobočka ↔ okres), `fallback_branch_id` pri pobočke
- `profiles` (užívateľ ↔ pobočka, **rola**: `makler`, `admin`, `financie`; **stav**: `aktivny`, `nepritomny` s dátumami, `neaktivny`)
- `clients`, `leads` (ocenenia), `nabory` (stav: `rozpracovany`, `ulozeny`, `nedohodnuty`), `lead_assignments` (história pridelení a odovzdaní)
- `document_templates`, `template_fields`, `client_documents` (vyplnená kópia, verzie), `document_rules` (podmienka → šablóna, povinná/odporúčaná)
- `financing` (stav: `nove`, `priprava`, `riesi_sa`, `vyriesene`, `nerieni_sa` + dôvod) a `financing_notes`
- `audit_log` (kto, kedy, čo; vrátane zobrazenia osobných údajov rolou `financie`)
Všetky tabuľky majú `org_id`. Plán popisuje pravidlá rolí presne, drž sa ho.

### 3. Migrácie a RLS (F4)
- **3.1** `0002_core.sql`: organizácie, pobočky, profily, role. Len pridávajúca.
- **3.2** `0003_crm.sql`: klienti, leady, nábory, pridelenia.
- **3.3** `0004_documents.sql`: šablóny, polia, vyplnené dokumenty, pravidlá.
- **3.4** `0005_financing.sql`: financovanie a poznámky.
- **3.5** `0006_audit.sql`: audit log.
- **3.6** **RLS testy** (`tests/rls/`): maklér A nevidí nábory makléra B; admin vidí všetko; `financie` vidí všetky nábory, ale nemôže meniť nábor ani dokumenty, len stav financovania a svoje poznámky; neprihlásený nevidí nič. Spúšťať v CI proti čistej databáze.
Po každej migrácii spusti testy.

### 4. Prihlásenie a role (F4)
- **4.1** Supabase Auth (e-mail + magic link / obnova hesla), odhlásenie. Session len cez httpOnly cookies.
- **4.2** Ochrana trás v `proxy.ts`/middleware: interná aplikácia na samostatnej ceste (napr. `app/(app)/…`), verejný web bez zmeny.
- **4.3** Pozvánka makléra adminom (admin zadá meno, e-mail, pobočku, rolu).
- **4.4** Stránka správy členov len pre admina: zoznam, zmena pobočky/roly/stavu (aktívny, neprítomný od–do, neaktívny).

### 5. Základné obrazovky (F4, F7)
Každá obrazovka s prázdnym stavom, ktorý vysvetlí, čo sa tu bude zobrazovať. Seed dáta len vymyslené.
- **5.1** Navigácia: CRM, Dokumenty, Postup, (Financie podľa roly).
- **5.2** Zoznam ocenení maklérovi (len jeho) + detail + tlačidlo „Vytvoriť nábor“ (predvyplní údaje z ocenenia).
- **5.3** Nábor: stavy Rozpracovaný / Uložený / Nedohodnutý (pýta dôvod a potvrdenie). **Archív nedohodnutých** s tlačidlom „Obnoviť nábor“.
- **5.4** Kategória **Nepridelené** s tlačidlom „Vziať si“: **atomicky** (prvý vyhráva, druhý dostane správu „už si ho vzal …“), **limit 1 za 24 h na makléra** (nastaviteľný, admina sa netýka), kontrola na serveri, nie len v UI.
- **5.5** **Odovzdať kolegovi**: výber kolegu + dôvod, prejdú poznámky aj dokumenty, história. Povolené aj do inej pobočky.
- **5.6** Fronta „Na pridelenie“ pre adminov (zatiaľ ručné pridelenie). Prepínač „ručne / striedavo“ zatiaľ len v nastaveniach, striedanie je neskôr.
- **5.7** Obrazovka **Financie** (rola `financie`): zoznam všetkých začatých náborov s filtrami (stav financovania, pobočka, maklér), zmena stavu, poznámky. Vidí meno a kontakt klienta, každé zobrazenie sa zapíše do auditu. Maklér vidí stav a poznámky na svojom nábore.
- **5.8** Knižnica **Dokumentov**: zoznam šablón s kategóriami a vyhľadávaním, akcie „Stiahnuť prázdnu“ a „Vyplniť pre klienta“. Šablóny len na čítanie (Storage politiky).
- **5.9** V nábore: sekcia dokumentov so **zoznamom navrhnutých** (Zmluva o sprostredkovaní predaja vždy prvá), makléri môžu pridať/odobrať, stiahnuť, upraviť vo Worde a **nahrať späť ako novú verziu**.

### 6. Dokončenie
- **6.1** Monitoring chýb bez osobných údajov v logoch (I-056).
- **6.2** Aktualizuj `README` rozia (spustenie, prostredia, testy, štruktúra interná/verejná časť).

## STAV PRÁCE
Po každej úlohe vypíš tabuľku: **úloha | hotovo | testy spustené | čo ostáva**. Keď niečo vyžaduje rozhodnutie majiteľa (napr. ostré nasadenie, plán Supabase, prístup), napíš to na koniec odpovede veľkými písmenami **POTREBUJEM ROZHODNUTIE**.
