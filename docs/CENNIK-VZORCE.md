# Cenník: odkiaľ sú čísla a ako sa počíta rozpätie

Kalkulačka na nacenime.sk ráta z jedného súboru podkladov. Tento dokument popisuje celú cestu od riadku v tabuľke po rozpätie na obrazovke.

## Súbory
| Súbor | Čo to je |
|---|---|
| `podklady.csv` | Podklady. Jeden riadok je jeden predaj alebo jeden inzerát. Stĺpce `zdroj,typ,obec,stav,cena,plocha`. **Nie je vo verejnom repe**, uchováva ho majiteľ. |
| `nacenime-cennik-podklady.xlsx` | Tie isté podklady v Exceli, s kalkulačkou a vzorcami na skúšanie. **Tiež mimo repa.** |
| `design_handoff_nacenime/data/nacenime-cennik.json` | Cenník, z ktorého číta web (len súhrnné kvartily). Vzniká z `podklady.csv`. Ručne sa neupravuje, okrem nastavení `prahy`, `zlavy`, `nahradne_okresy`. |

Repo je verejné. Jednotlivé inzeráty z cudzej inzercie a vlastné predaje s presnou cenou sa preto do neho nedávajú (rozhodnutie majiteľa 16. 11. 2026). Zverejnený je len súhrnný cenník. Priečinok `docs/cennik/` je v `.gitignore`, aby sa podklady nenahrali omylom.

Stav podkladov k 16. 11. 2026: 1 031 riadkov. 16 vlastných predajov NajBývanie (byty v Ružomberku, roky 2025 a 2026) a 1 015 ponúk z inzercie (okresy Ružomberok, Martin, Liptovský Mikuláš, Dolný Kubín, stiahnuté 11. 11. 2026). Podklady neobsahujú mená, adresy ani odkazy.

## Ako zmeniť ceny na webe
1. Uprav `podklady.csv` (pridaj, oprav alebo zmaž riadky). Pracovná kópia môže ležať v `docs/cennik/podklady.csv`, Git ju ignoruje.
2. Spusti `php backend/bin/zostav-cennik.php docs/cennik/podklady.csv --zapis --nahradit-vsetko`. Bez zápisu, len porovnanie so súčasným cenníkom: `--porovnaj`.
3. Spusti `php backend/bin/kontrola-cennika.php` a testy.
4. Pull request. Po zlúčení do `main` sa web nasadí sám.

## Vzorce

**Cena za m² jedného záznamu**
`m2 = cena / plocha`. Pri byte, apartmáne, dome a chate je plocha úžitková. Pri pozemku je to plocha pozemku.

**Skupina**
Kľúč má tvar `zdroj|typ|lokalita|stav`. Lokalita je obec alebo `okres X`. Stav je jeden zo štyroch alebo `všetky`.

Každý záznam ide do štyroch skupín: obec so stavom, obec `všetky`, okres so stavom, okres `všetky`. Záznam bez stavu a každý pozemok ide len do skupín `všetky`.

**Kvartily skupiny**
Ceny za m² sa zoradia vzostupne. Pre `p` = 0,25 / 0,5 / 0,75:
```
pozícia = (n − 1) × p
dolný   = celá časť pozície
hodnota = x[dolný] + (pozícia − dolný) × (x[dolný + 1] − x[dolný])
```
Je to lineárna interpolácia, rovnaká ako `QUARTILE.INC` v Exceli. Výsledok sa zaokrúhli na celé eurá. Skupina s menej než 3 záznamami sa do cenníka nezapíše.

**Kedy sa skupina použije**
Predaje potrebujú aspoň 4 záznamy, ponuky aspoň 8 (`prahy` v cenníku).

**Poradie hľadania**
Berie sa prvá skupina, ktorá spĺňa počet:
1. Predaje, obec, stav
2. Ponuky, obec, stav
3. Predaje, okres, stav
4. Ponuky, okres, stav
5. Predaje, obec, všetky
6. Ponuky, obec, všetky
7. Predaje, okres, všetky
8. Ponuky, okres, všetky

Ak nevyhovuje žiadna a okres má v cenníku náhradný okres (`nahradne_okresy`), rovnaké hľadanie na úrovni okresu prebehne v susednom okrese a výsledok sa označí ako približný (veta „Približný odhad podľa … v susednom okrese …“). Dnes: Tvrdošín a Námestovo podľa Dolného Kubína, Turčianske Teplice podľa Martina. Vlastné dáta okresu majú vždy prednosť.

Ak nevyhovuje nič, web ukáže „Na odhad tu máme málo predajov" a ponúkne makléra.

**Rozpätie**
```
z predajov:  od = q1 × výmera                      do = q3 × výmera
z ponúk:     od = q1 × výmera − najväčšia zľava    do = q3 × výmera − najmenšia zľava
```
Obe čísla sa zaokrúhlia na tisíce eur a nikdy nejdú pod nulu.

**Zľavy z ponukovej ceny** (`zlavy` v cenníku)
| Typ | Najmenšia | Najväčšia |
|---|---|---|
| Byt, Apartmán | 1 000 € | 5 000 € |
| Dom, Chata | 1 000 € | 10 000 € |
| Pozemok | 0 € | 0 € |

Zľavy pri bytoch a domoch určil majiteľ podľa praxe. Pri pozemku zľava určená nebola.

## Príklad
Byt 66 m², Ružomberok, kompletná rekonštrukcia. Skupina `Predaje|Byt|Ružomberok|Kompletná rekonštrukcia` má 6 záznamov, takže sa použije hneď v prvom kroku.
`od = q1 × 66`, `do = q3 × 66`, po zaokrúhlení 131 000 až 145 000 €.

## Ako sa čistili ponuky
Vyradené boli dopyty, prenájmy, inzeráty bez ceny alebo plochy, duplikáty (rovnaký okres, typ, cena a plocha) a záznamy mimo hraníc:
- byt a apartmán: plocha 15 až 250 m², cena 800 až 5 500 €/m²
- dom a chata: plocha 40 až 400 m², cena 300 až 5 000 €/m²
- pozemok: plocha 100 až 20 000 m², cena 3 až 400 €/m²

Obec a stav pri ponukách sú odhadnuté z textu inzerátu.

## Známe slabiny
- Domy majú široké rozpätia, lebo cena domu závisí od pozemku viac než od úžitkovej plochy.
- Novostavby v Ružomberku majú len 3 vlastné predaje, takže padajú do skupiny `všetky` a vychádzajú nízko.
- Okresy Tvrdošín, Námestovo a Turčianske Teplice nemajú vlastné dáta, odhad je len približný podľa susedného okresu.
- Ponuky sú z cudzej inzercie. Ich použitie má pred ostrým spustením posúdiť právnik.
