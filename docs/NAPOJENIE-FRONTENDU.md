# Napojenie frontendu na API

Frontend (`Nacenime.dc.html`) sa nemení. Toto je zmluva pre toho, kto ho napája na backend. Úplný popis je v `openapi.yaml`.

## Všeobecne
- Všetky `POST` idú ako `Content-Type: application/json`, inak 415.
- Chyba validácie: `422` s `{"chyby": {"pole": "veta"}}`. Veta je už finálny text pre používateľa (rovnaké znenie ako v README dizajnu), zobrazí sa pod poľom.
- `429` = príliš veľa požiadaviek (limit na IP), `5xx` = chyba servera. Texty pre tieto stavy sú zatiaľ nenakreslené (pozri `PODKLADY.md`).

## Obrazovky a endpointy
| Obrazovka / akcia | Volanie |
|---|---|
| Úvod, Mesto: písanie adresy | `GET /api/adresa?q=…&token=…` od 3 znakov, oneskorenie 300 ms po poslednom klávese |
| Výber návrhu | `GET /api/adresa/detail?id=…&token=…`, potom sa token zahodí |
| Odpoveď `zaloha: true` (Google nefunguje) | zobraziť polia „Ulica a číslo“ a „Obec“, obec overiť `GET /api/obec?obec=…` po opustení poľa |
| Potvrdenie adresy | údaje z detailu (`ulica`, `psc`, `obec`, `lat`, `lng`); `v_regione: false` = veta „Zatiaľ oceňujeme len Liptov, Turiec a Oravu.“ a po potvrdení obrazovka Mimo regiónu |
| Posun špendlíka | uložiť nové `lat`/`lng`, pošlú sa v `adresa` pri odhade |
| Kalkulačka, „Zobraziť odhad“ | `POST /api/odhad`, uloží si `odhad_id` |
| Výsledok: Odhad v PDF | `POST /api/kontakt/pdf` s `email`, `suhlas`, `odhad_id` |
| Výsledok: Cena od makléra | `POST /api/kontakt/makler` s `telefon`, `odhad_id` |
| Mimo regiónu | `POST /api/kontakt/mimo-regionu` s `email` |

## Session token pre Google
Jedna relácia = písanie plus výber jednej adresy. Vygeneruj náhodný reťazec `[A-Za-z0-9_-]`, 8 až 64 znakov (napr. `crypto.randomUUID()` bez pomlčiek sa hodí), posielaj ho v `token` pri každom hľadaní aj pri detaile. Po výbere vygeneruj nový.

## Odhad: čo poslať
`typ`, `obec`, `vymera` povinné. `stav` pri pozemku **neposielať**. Pri adrese zo zálohy `adresa.zo_zalohy: true`, pri pozemku bez adresy `adresa.bez_adresy: true` (a `obec` z poslednej otázky). Výmeru možno poslať ako text s čiarkou (`"66,5"`).

## Výsledok podľa `stav`
| `stav` | Čo zobraziť |
|---|---|
| `ok` | H1 `od` až `do` (tisíce a „€“ oddeliť pevnou medzerou), stupnica, `poznamka`, `veta`, bloky PDF aj maklér |
| `málo dát` | `nadpis` a `veta`, len blok Cena od makléra |
| `maklér` | `nadpis` a `veta`, len blok Cena od makléra, bez stupnice a PDF |
| `mimo regiónu` | obrazovka Mimo regiónu (`odhad_id` nie je) |

Meno makléra je v `makler`.

## Formuláre
- **Honeypot:** do každého kontaktného formulára patrí skryté pole `web` (mimo tabu, `aria-hidden`, `tabindex=-1`, `autocomplete=off`). Musí zostať prázdne, odošli ho v tele.
- **Súhlas** (`suhlas`) posielaj len ako boolean `true` pri zaškrtnutí. Predvolene nezaškrtnuté.
- Úspech: `200` s `{"sprava": "…"}`, text je už finálny.
