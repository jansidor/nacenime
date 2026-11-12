# Podklady, ktoré treba doplniť (nacenime.sk)

## A. Blokuje funkčnosť
1. **Cenníkové dáta (predaje a ponuky) pre okresy Tvrdošín, Turčianske Teplice a Námestovo.** Pre tieto okresy nie je žiadna skupina na úrovni okresu a takmer žiadna na úrovni obce, takže ľudia odtiaľ dostanú „málo dát“. Treba počet, q1, medián, q3 v €/m² podľa typu (Byt, Apartmán, Dom, Chata, Pozemok), stavu a lokality, vo formáte `design_handoff_nacenime/data/nacenime-cennik.json`.
2. ~~Zoznam obcí vo všetkých 7 okresoch~~ **Hotovo** (213 obcí, zdroj `backend/data/obce-siedmich-okresov.csv`). Na potvrdenie: `Dovalovo` (okres Liptovský Mikuláš) je v pôvodných dátach, ale nie v novom zozname. Ponechané, treba potvrdiť, či ide o platnú lokalitu.
3. **Google Cloud (projekt `nacenime-sk`):** API kľúč pre Places API (New) a Maps JavaScript API, obmedzený na `nacenime.sk` a `*.nacenime.sk`, plus testovacia a lokálna doména. Kľúč nepísať do chatu ani do repa, ide do `backend/.env` (`NACENIME_GOOGLE_API_KEY`).
4. **SMTP údaje na odosielanie PDF:** server, port, šifrovanie, používateľ, heslo a odosielacia adresa (napr. `noreply@nacenime.sk`). Idú do `backend/.env` (`NACENIME_SMTP_*`, `NACENIME_MAIL_FROM`).
5. **Meno makléra** (dnes „[MENO MAKLÉRA]“), hodnota `NACENIME_MAKLER_NAME`.

## B. Obsah a texty
6. **Fotky** Martina, Dolného Kubína a Námestova (v štýle fotiek Ružomberka a Liptovského Mikuláša).
7. **Stránky miest** (5 miest): priemerná cena bytu a domu za m², počet predajov, text o štvrtiach a cenách, riadok „Naposledy predané“ (typ, výmera, štvrť, cena za m²).
8. **Právne texty:** Ochrana osobných údajov, Cookies, Kontakt. Aj znenie súhlasu s kontaktovaním makléra.
9. **Texty chýbajúcich stavov** (zatiaľ nenakreslené): načítava sa, žiadny výsledok adresy, chyba servera pri odhade, PDF a makléri, odosielanie, mapa sa nenačítala, prázdne odoslanie adresy.

## C. Rozhodnutia
10. **Typy bez rozpätia:** ktoré typy nehnuteľností (ak nejaké) majú namiesto rozpätia ukázať len „Cenu vám povie maklér“ (`NACENIME_TYPY_BEZ_ROZPATIA`, predvolene žiadne).
11. **Retencia osobných údajov:** ako dlho uchovávať kontakty (predvolene 24 mesiacov) a schválenie postupu mazania podľa GDPR.
12. **Zdroj dát a aktualizácia cenníka:** odkiaľ pochádzajú predaje a ponuky, ako často sa cenník obnovuje a akou metódou sa počítajú kvartily (aby sa dal skript na aktualizáciu napísať).

## D. Nasadenie a GitHub
13. **Hosting:** server, doména, verzia PHP (min. 8.1 s `mbstring`, `pdo_sqlite`, `curl`, odporúčané `intl`), či je za reverse proxy (`NACENIME_TRUSTED_PROXY=1`), kde bežia cron úlohy.
14. **GitHub:** schválenie pull requestu od `jansidor` a zapnutie „Require review from Code Owners“ na `main` v pôvodnom repe.
