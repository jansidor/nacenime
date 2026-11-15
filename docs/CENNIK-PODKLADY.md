# Cenové dáta pre nové okresy: čo pripraviť

Pre okresy **Tvrdošín, Turčianske Teplice a Námestovo** kalkulačka zatiaľ nemá ceny, takže tam ukáže „Na odhad tu máme málo predajov“. Stačí pripraviť jednu tabuľku (Excel alebo CSV) podľa tohto návodu.

Vzor: [`sablona-cennik.csv`](sablona-cennik.csv) (tri ukážkové riadky, pred odoslaním ich zmažte).

## Stĺpce
Jeden riadok = jeden predaj alebo jeden inzerát.

| Stĺpec | Čo tam patrí | Príklad |
|---|---|---|
| `zdroj` | `Predaje` (skutočne predané, napr. vlastné obchody, kataster) alebo `Ponuky` (inzeráty) | `Predaje` |
| `typ` | `Byt`, `Apartmán`, `Dom`, `Chata` alebo `Pozemok` | `Byt` |
| `obec` | názov obce presne ako v zozname nižšie (diakritika a veľké písmená nevadia) | `Trstená` |
| `stav` | `Pôvodný`, `Čiastočná rekonštrukcia`, `Kompletná rekonštrukcia` alebo `Novostavba`. Pri pozemku nechajte prázdne. | `Pôvodný` |
| `cena` | celková cena v eurách, len číslo (bez €, medzier a bodiek) | `95000` |
| `plocha` | úžitková plocha v m² (pri pozemku plocha pozemku), len číslo | `62` |

Oddeľovač môže byť čiarka aj bodkočiarka (Excel „CSV UTF-8“ je v poriadku).

## Koľko dát treba
- Skupina (napr. „Predaje, Byt, okres Tvrdošín, Pôvodný“) sa použije, keď má aspoň **4 predaje** alebo **8 ponúk**. Pri menšom počte kalkulačka vezme širšiu skupinu: najprv celý okres, potom údaje bez rozlíšenia stavu.
- Rozumné minimum na okres a typ: **10 až 15 predajov** alebo **20 a viac ponúk**. Čím viac, tým užšie a presnejšie rozpätie.
- Predaje sú cennejšie ako ponuky. Pri ponukách kalkulačka odpočíta bežnú zľavu pri predaji.
- Nepridávajte rovnaký byt dvakrát (napr. ten istý inzerát z dvoch portálov).

## Obce v okresoch
- **Tvrdošín** (15): Brezovica, Habovka, Hladovka, Liesek, Nižná, Oravský Biely Potok, Podbiel, Suchá Hora, Trstená, Tvrdošín, Vitanová, Zuberec, Zábiedovo, Čimhová, Štefanov nad Oravou
- **Turčianske Teplice** (26): Abramová, Blažovce, Bodorová, Borcová, Brieštie, Budiš, Dubové, Horná Štubňa, Háj, Ivančiná, Jasenovo, Jazernica, Kaľamenová, Liešno, Malý Čepčín, Moškovec, Mošovce, Ondrašová, Rakša, Rudno, Sklené, Slovenské Pravno, Turček, Turčianske Teplice, Veľký Čepčín, Čremošné
- **Námestovo** (24): Babín, Beňadovo, Bobrov, Breza, Hruštín, Klin, Krušetnica, Lokca, Lomná, Mútne, Novoť, Námestovo, Oravská Jasenica, Oravská Lesná, Oravská Polhora, Oravské Veselé, Rabča, Rabčice, Sihelné, Vasiľov, Vavrečka, Zubrohlava, Zákamenné, Ťapešovo

Obec mimo zoznamu sa preskočí a skript ju vypíše.

## Pre technika: ako dáta pridať
```bash
php backend/bin/zostav-cennik.php data.csv                      # len súhrn, nič sa nemení
php backend/bin/zostav-cennik.php data.csv --zapis --doplnit    # nahradí len okresy, ktoré sú v data.csv
php backend/bin/kontrola-cennika.php
```
`--doplnit` je dôležité: bez neho by sa prepísal celý cenník a zmizli by ceny ostatných okresov (skript to odmietne). Potom testy a pull request.
Metóda kvartilov (lineárna interpolácia) je predpoklad, ktorý treba potvrdiť s tým, kto pripravoval pôvodný cenník.
