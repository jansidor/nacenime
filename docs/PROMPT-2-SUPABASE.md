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

## STAV K 24. 11. 2026 (večer): čo je hotové, to NEPÍŠ znova
- **Databáza a oprávnenia** (migrácie `0002` až `0008`, testy `supabase/tests/rls_crm*.sql`, CI) sú hotové a overené na testovacom Supabase. Nové migrácie píš len ak obrazovka naozaj potrebuje chýbajúci stĺpec alebo funkciu, najprv to napíš majiteľovi. Každá nová funkcia v schéme `public` musí mať `revoke all … from public, anon, authenticated` a grant len tam, kde treba (test N9 to stráži). Každé nové hlásenie `raise exception` zaraď v `lib/crm/errors.ts` medzi `KNOWN` alebo `INTERNAL` (test to stráži).
- **Aplikácia v1** je v `app/crm/` (vlastný koreňový layout, `noindex`): prihlásenie, menu podľa roly, ocenenia (+ detail s panelom vráteného klienta a „Vytvoriť nábor“), Nepridelené s „Vziať si“, pridelenie adminom, nábory (+ detail: úprava, poznámky, navrhnuté dokumenty, odovzdanie kolegovi, archív a obnova), financovanie (+ detail: kontakt cez audit, stav, poznámky). Vzor kódu: `app/crm/actions.ts` (server actions volajú databázové funkcie), `lib/crm/session.ts` (`requireStaff`), `components/crm-ui.tsx`.
- Funkcie dostupné z aplikácie (každá si kontroluje rolu): `claim_unassigned`, `assign_valuation`, `create_mandate_from_valuation`, `close_mandate_unagreed`, `restore_mandate`, `handoff_mandate`, `handoff_valuation`, `list_colleagues`, `set_my_availability`, `returning_client_check`, `link_returning_client`, `suggest_documents`, `set_financing_status`, `finance_view_client`, `branch_for_municipality`. Len server (nie z aplikácie): `auto_assign_valuation`, `anonymize_*`, `ingest_nacenime_event`, `run_retention`.
- Prístup k duplikátu CRM, import a synchronizácia šablón robí kolega (prompt 1). Nezasahuj do nich.
- **Aplikácia nebola nikdy skúšaná v prehliadači proti Supabase.** Kým ju majiteľ nenasadí, over zmeny typecheckom, testami a buildom a výslovne napíš, čo si v prehliadači nevidel.

## ÚLOHY (zadávaj po jednej, v tomto poradí; po každej pull request)

### 0. Prevzatie
- **0.1** Prečítaj plán a `supabase/README.md`. Lokálne spusti postup „Testy bez Supabase“ a `npm test`, `npm run typecheck`, `npm run build`. Nič nemeň.

### 1. Moja dostupnosť (maklér)
- **1.1** Na úvode CRM alebo v hlavičke: stav „dostupný / neprítomný do dátumu“ cez `set_my_availability`. Neprítomný maklér sa nepridelí automaticky a nemožno mu odovzdať nábor.

### 2. Správa členov (admin)
- **2.1** `/crm/clenovia/` (len admin): zoznam zamestnancov (`staff`), zmena roly, pobočky, aktívny/neaktívny, dostupnosť (admin smie meniť cez RLS).
- **2.2** Pozvánka nového zamestnanca: server action len pre admina overí rolu, cez Supabase Admin API (`auth.admin.inviteUserByEmail`, servisný kľúč len na serveri) pozve e-mail a vloží riadok do `staff`. E-mail a meno nikdy do logov.

### 3. Nastavenia (admin)
- **3.1** `/crm/nastavenia/`: prepínač `assignment_mode` (ručne / striedavo) a zobrazenie ostatných nastavení z `app_settings` (lehoty uchovávania len na čítanie s upozornením, že ich zmena vyžaduje aj zmenu zásad ochrany údajov).

### 4. Archív a hľadanie
- **4.1** V zozname náborov filter podľa stavu (aktívne, archív, predané) a hľadanie podľa mena klienta alebo názvu.
- **4.2** Označenie náboru „predaný“ (stav `predany`) s potvrdením.

### 5. Dokončenie
- **5.1** Aktualizuj `supabase/README.md` (obrazovky, cesty) a `docs/PROSTREDIA.md`.

## STAV PRÁCE
Po každej úlohe vypíš tabuľku: **úloha | hotovo | testy spustené | čo si neoveril | čo ostáva**. Keď niečo vyžaduje rozhodnutie majiteľa, napíš na koniec odpovede veľkými písmenami **POTREBUJEM ROZHODNUTIE**.
