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

## STAV K 24. 11. 2026 (dôležité: čo už je hotové, to NEPÍŠ znova)
- **Dátový model a RLS sú hotové.** Migrácie `0002_crm_core.sql`, `0003_hardening.sql` sú v rozia PR #1 (otvorený) a `0004_hardening_handoff.sql` je vo vetve `claude/nifty-dijkstra-aqmzuc` repa rozia (nad PR #1). Obsahujú pobočky, zamestnancov s rolami (`broker`, `admin`, `finance`), klientov, ocenenia, nábory, financovanie, odovzdanie, šablóny, pravidlá dokumentov, vyplnené dokumenty a audit.
- **Testy oprávnení sú hotové:** `supabase/tests/rls_crm.sql`, `rls_crm_2.sql`, simulácia Supabase `bootstrap_local.sql`. CI pre rozia je v `.github/workflows/ci.yml`, návod na prostredia v `docs/PROSTREDIA.md`.
- **Funkcie, ktoré aplikácia volá** (cez Supabase RPC, každá si sama kontroluje rolu; ostatné funkcie sú interné):
  `claim_unassigned(valuation)`, `handoff_mandate(mandate, to, note)`, `handoff_valuation(valuation, to, note)`, `list_colleagues()`,
  `set_my_availability(stav, do_dátumu)`, `set_financing_status(mandate, stav, dôvod)`, `finance_view_client(client)` (zapisuje do auditu).
  Od migrácie `0005_lifecycle.sql` (vo vetve `claude/nifty-dijkstra-aqmzuc` repa rozia, ak ju už majiteľ nezlúčil): `close_mandate_unagreed(nábor, dôvod)` (nedohodnutý → archív), `restore_mandate(nábor)`,
  `returning_client_check(ocenenie)` a `link_returning_client(ocenenie, klient, obnovený_nábor)` (vrátený klient so zmenami „pôvodne → teraz“), `assign_valuation(ocenenie, maklér, poznámka)` (len admin, fronta „Na pridelenie“),
  `suggest_documents(nábor)` (povinné a odporúčané dokumenty, `template_missing` ak šablóna ešte nie je), `branch_for_municipality(obec, okres)`. Nastavenia v `app_settings` (`assignment_mode`, `archive_retention_months`, `return_match_threshold`) mení admin.
  Len server (nie z aplikácie): `auto_assign_valuation`, `anonymize_archived`.
  Priame zápisy do `audit_log`, do financovania na nábore a do odhadu ocenenia sú zakázané databázou.
- Príjem webhooku z nacenime (`inbound_events`) a import z duplikátu robí kolega (prompt 1). Nezasahuj do nich.
- Nové migrácie (`0005_…` a ďalšie) píš len ak ti nejaká obrazovka chýbajúce stĺpce vyžaduje, a najprv to napíš majiteľovi. Každá nová funkcia v schéme `public` musí mať `revoke all … from public, anon, authenticated` a grant len tam, kde treba (pozri `supabase/README.md`, test N9).

## ÚLOHY (zadávaj po jednej, v tomto poradí)

### 0. Prevzatie (najprv)
- **0.1** Prečítaj plán a `supabase/README.md`. Lokálne spusti postup „Testy bez Supabase“ z README a napíš výsledok. Nič nemeň.
- **0.2** Skontroluj, či `npm run build`, `npm run typecheck`, `npm test` prechádzajú.

### 1. Prostredie (zvyšok)
- **1.1** Hotové (CI). Skontroluj len, že workflow na GitHube prešiel; ak nie, oprav.
- **1.2** Pridaj `docs/ADR-000-sablona.md` (kontext, rozhodnutie, dôsledky) a krátky `docs/README.md` s odkazom na plán a `PROSTREDIA.md`.
- **1.3** Hotové (`docs/PROSTREDIA.md`). Staging Supabase projekt zakladá majiteľ.

*(Úlohy 2 a 3, dátový model a migrácie, sú hotové: pozri STAV vyššie.)*

### 4. Prihlásenie a role (F4)
- **4.1** Supabase Auth (e-mail + magic link / obnova hesla), odhlásenie. Session len cez httpOnly cookies.
- **4.2** Ochrana trás v `proxy.ts`/middleware: interná aplikácia na samostatnej ceste (napr. `app/(app)/…`), verejný web bez zmeny.
- **4.3** Pozvánka makléra adminom (admin zadá meno, e-mail, pobočku, rolu).
- **4.4** Stránka správy členov len pre admina: zoznam, zmena pobočky/roly/stavu (aktívny, neprítomný od–do, neaktívny).

### 5. Základné obrazovky (F4, F7)
Každá obrazovka s prázdnym stavom, ktorý vysvetlí, čo sa tu bude zobrazovať. Seed dáta len vymyslené.
- **5.1** Navigácia: CRM, Dokumenty, Postup, (Financie podľa roly).
- **5.2** Zoznam ocenení maklérovi (len jeho) + detail + tlačidlo „Vytvoriť nábor“ (predvyplní údaje z ocenenia).
- **5.3** Nábor: stavy Rozpracovaný / Uložený / Nedohodnutý. „Nedohodnutý“ volá `close_mandate_unagreed()` (pýta dôvod a potvrdenie). **Archív nedohodnutých** (nábory so stavom `nedohodnuty`) s tlačidlom „Obnoviť nábor“ → `restore_mandate()`. Pri novom ocenení zobraz upozornenie „klient sa vrátil“ z `returning_client_check()` so zmenami pôvodne → teraz a tlačidlami „Obnoviť pôvodný nábor“ a „Nový nábor pre tohto klienta“ (`link_returning_client()`).
- **5.4** Kategória **Nepridelené** s tlačidlom „Vziať si“: volaj `claim_unassigned()` (atomicky, prvý vyhráva, limit 1 za 24 h kontroluje databáza; chybu zobraz používateľovi po slovensky). Ocenenia bez pobočky vidia všetci makléri, ostatné maklér danej a záložnej pobočky.
- **5.5** **Odovzdať kolegovi**: zoznam z `list_colleagues()` (meno, pobočka, dostupnosť), volaj `handoff_mandate()` alebo `handoff_valuation()` s poznámkou. Prejdú poznámky aj dokumenty, história a audit sú v databáze. Povolené aj do inej pobočky, nie neprítomnému. Maklér si nastaví dovolenku/PN cez `set_my_availability()`.
- **5.6** Fronta „Na pridelenie“ pre adminov (zatiaľ ručné pridelenie). Prepínač „ručne / striedavo“ zatiaľ len v nastaveniach, striedanie je neskôr.
- **5.7** Obrazovka **Financie** (rola `financie`): zoznam všetkých začatých náborov s filtrami (stav financovania, pobočka, maklér), zmena stavu cez `set_financing_status()`, poznámky. Meno a kontakt klienta čítaj cez `finance_view_client()` (zapíše do auditu a pustí len klienta s náborom). Maklér vidí stav a poznámky na svojom nábore.
- **5.8** Knižnica **Dokumentov**: zoznam šablón s kategóriami a vyhľadávaním, akcie „Stiahnuť prázdnu“ a „Vyplniť pre klienta“. Šablóny len na čítanie (Storage politiky).
- **5.9** V nábore: sekcia dokumentov so **zoznamom navrhnutých** (z `suggest_documents()`) (Zmluva o sprostredkovaní predaja vždy prvá), makléri môžu pridať/odobrať, stiahnuť, upraviť vo Worde a **nahrať späť ako novú verziu**.

### 6. Dokončenie
- **6.1** Monitoring chýb bez osobných údajov v logoch (I-056).
- **6.2** Aktualizuj `README` rozia (spustenie, prostredia, testy, štruktúra interná/verejná časť).

## STAV PRÁCE
Po každej úlohe vypíš tabuľku: **úloha | hotovo | testy spustené | čo ostáva**. Keď niečo vyžaduje rozhodnutie majiteľa (napr. ostré nasadenie, plán Supabase, prístup), napíš to na koniec odpovede veľkými písmenami **POTREBUJEM ROZHODNUTIE**.
