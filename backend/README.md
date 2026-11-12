# Backend

PHP port výpočtu odhadu z `design_handoff_nacenime/reference/odhad.js`.

- `src/Odhad.php` – trieda `Nacenime\Odhad` (bez závislostí, PHP 8.1+, `mbstring`)
- `tests/run.php` – testy (`php backend/tests/run.php`)
- `tests/parity.sh` – porovnanie s JS referenciou na všetkých kombináciách (`backend/tests/parity.sh`, treba `node` a `php`)

Cenník sa číta zo `design_handoff_nacenime/data/nacenime-cennik.json`. V produkcii patrí mimo verejného priečinka.
