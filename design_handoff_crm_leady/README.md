# Handoff: CRM Leady (nacenime.sk)

## Prehľad
Vnútorný systém pre leady z kalkulačky nacenime.sk. Prihlásenie, kanban a tabuľka leadov, detail leadu, moje úlohy (maklér), prehľad a návštevnosť, členovia tímu (správca). Role: **Správca** vidí všetko, **Maklér** len leady pridelené jemu.

## O súboroch
`CRM Leady.dc.html` je **dizajnová referencia v HTML**, nie produkčný kód. Postavte obrazovky nanovo v cieľovom prostredí so skutočným prihlásením a databázou. Prototyp beží na vymyslených dátach (`seed()`), prihlásenie je simulované, e-maily sa neodosielajú, návštevnosť je generovaná (`buildTraffic()`).

Otvára sa priamo v prehliadači, `support.js` musí ležať vedľa (v produkcii ho netreba). Props: `startScreen` (`prihlasenie` | `sprava` | `makler`), `defaultView` (`kanban` | `tabulka`), `brand` (text loga).

## Vernosť
**Hi-fi pre štruktúru a správanie.** Vizuálna vrstva CRM ešte **nebola prepísaná** na novú paletu a písmo verejného webu (pozri nižšie). Rozhodnite s dizajnérom, či sa zjednotí.

## Dizajnové hodnoty (aktuálne v CRM)
- Farby: pozadie `#f6f4ef`, text `#11122f`, primárna `#272361`, červená `#d32441` (len hrana a grafy), chyba `#8f1229`, tichý text `#5c5d75`, rámy polí `#8a8899`, tiché plochy `#dcd9d0`, hover `#e9e6dc`, biele karty.
- Písmo: Archivo 800/900 nadpisy, Space Grotesk 400/500/700 text, základ 15 px. Ikony Phosphor 2.1.1 (CDN).
- Polia a tlačidlá min. 44 px, polomer 8 px (karty 12 px). Pohyb vypnutý pri `prefers-reduced-motion`.
- **Rozdiel oproti verejnému webu:** web používa len Archivo, polomer 12 px, polia 60 px, bez ikon pri možnostiach. CRM ešte používa Space Grotesk, Phosphor ikony a 44 px prvky.

## Obrazovky
1. **Prihlásenie** – e-mail, heslo, „Prihlásiť“, „Zabudnuté heslo“, „Prihlásiť odkazom na e-mail“. Chyba: „Prihlásenie sa nepodarilo. Skontrolujte e-mail a heslo.“ Odpoveď na zabudnuté heslo je rovnaká, či účet existuje alebo nie. Tlačidlá „Ako správca“ / „Ako maklér“ sú len pre ukážku.
2. **Leady** – prepínač Kanban / Tabuľka, hľadanie (meno, adresa, lokalita, e-mail, telefón od 3 číslic, bez diakritiky), filtre (maklér, stav, lokalita, typ, zdroj, prioritné, po termíne), počet „N leadov z M“, export CSV.
   - Kanban: stĺpce podľa stavu (správca 7, maklér 6 bez „Nový“). Presun ťahaním alebo cez detail. Karta: meno, typ a lokalita, odhad, prijatý, maklér, alarm, prioritný, „nevolať“.
   - Tabuľka: triediteľné stĺpce. Pod 760 px sa zobrazí zoznam kariet.
3. **Detail leadu** (panel) – kontakt, nehnuteľnosť, odhad, zdroj, stav, maklér, ďalší krok, poznámky, história, pri „Predané“ cena a dátum, pri „Stratené“ dôvod, „Vymazať údaje klienta“. Zatvára Esc.
4. **Moje úlohy** (maklér) – leady zoskupené: Po termíne, Dnes, Zajtra, Neskôr, Bez ďalšieho kroku.
5. **Prehľad** (správca) – záložky Leady a Návštevnosť.
   - Leady: obdobie (Všetko / 30 / 7 dní), dlaždice (Nové, V riešení, Predané, Stratené, Prvý kontakt), konverzia po maklérovi, dôvody straty, lokality.
   - Návštevnosť: obdobie (Dnes / 7 / 30 / vlastné), 4 dlaždice s porovnaním s predošlým obdobím, lievik, graf po dňoch, odchody po krokoch, zariadenia, tabuľky zdrojov a stránok.
6. **Členovia** (správca) – zoznam tímu, pozvánka (meno + e-mail), odstránenie makléra s povinným prehodením jeho aktívnych leadov, záznam aktivít (audit).

## Pravidlá a stavy
- Stavy: Nový → Pridelený → Kontaktovaný → Stretnutie → Zmluva → Predané; bokom Stratené.
- Pridelenie makléra z „Nový“ ho posunie na „Pridelený“; zrušenie pridelenia z „Pridelený“ vráti na „Nový“. Lead s maklérom nemôže byť „Nový“. Bez makléra nemožno ísť inam ako „Nový“.
- „Stratené“ vyžaduje dôvod: len zvedavý, nepredáva · predáva sám · iná realitná kancelária · nesúhlas s cenou · nedovolaný · mimo regiónu · iné (s textom).
- Alarm červený: „Nový“ nepridelený viac ako 2 h; „Pridelený“ nekontaktovaný viac ako 24 h od pridelenia. Alarm oranžový: ďalší krok po termíne.
- Klient bez súhlasu s telefonátom: označenie „nevolať“, odkaz „volať“ sa nezobrazí.
- Každá zmena zapisuje do histórie leadu (kto, kedy, čo). Dôležité akcie idú aj do záznamu aktivít.
- Pridelenie odosiela makléra e-mail s upozornením. Uloženie kroku vytvorí pripomienku e-mailom.
- Zmazanie údajov: meno, telefón, e-mail, adresa a poznámky sa nenávratne odstránia, v histórii zostane záznam.

## Dátový model lead (z `seed()`)
`id`, `cislo` (LD-2026-0001), `meno`, `telefon`, `email`, `suhlas` (súhlas s telefonátom), `prijaty` (čas), `adresa`, `lokalita`, `typ` (byt | dom | pozemok), `vymera`, `izby`, `stavNeh`, `dalsie`, `od` a `do` (odhad v tis. €), `odoslany` (kedy odišiel odhad), `stav`, `pridelene` (id makléra), `pridelenyAt`, `prio`, `krokText`, `krokAt`, `cena`, `datum`, `reason`, `reasonText`, `max` (najvyšší dosiahnutý krok, pre konverziu), `firstContact`, `zdroj`, `stranka`, `notes[]`, `history[]`.
Člen tímu: `id`, `meno`, `email`, `rola` (Správca | Maklér). Audit: `t`, `who`, `text`.

## Napojenie na produkciu
- Lead vzniká z kalkulačky (výsledok → „Zavolajte mi“ alebo súhlas pri PDF). Mapovanie polí kalkulačky na lead treba doplniť.
- Prihlásenie: heslo min. 12 znakov, magic link, obnova hesla. V prototype sa kontroluje len dĺžka.
- Oprávnenia vynucovať na serveri (maklér nesmie dostať cudzie leady cez API), nielen skrývaním v rozhraní.
- Návštevnosť, zdroje a stránky: vymyslené dáta, treba skutočnú analytiku.
- Export CSV: stĺpce Číslo, Prijatý, Meno, Telefón, E-mail, Súhlas s telefonátom, Lokalita, Typ, Odhad od, Odhad do, Stav, Maklér, Ďalší krok, Prioritný; oddeľovač `;`, UTF-8 s BOM; exportuje aktuálne filtrovaný zoznam a zapíše do auditu.

## Súbory
- `CRM Leady.dc.html` – prototyp
- `support.js` – runtime prototypu (netreba do produkcie)
