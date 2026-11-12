# nacenime.sk

## Pravidlo č. 1: frontend je zmrazený
Nemeň vzhľad ani texty. Nedotýkaj sa týchto súborov:
- `design_handoff_nacenime/Nacenime.dc.html`
- `design_handoff_nacenime/Nacenime Prehlad.dc.html`
- `design_handoff_nacenime/support.js`
- `design_handoff_nacenime/img/`

Pracuj len na backende, dátach a testoch (PHP endpointy, `reference/`, `data/`).
Ak úloha vyžaduje zásah do frontendu, zastav sa a opýtaj sa. Zdroj pravdy pre dizajn je `design_handoff_nacenime/README.md`.

## Ochrany
- `.githooks/pre-commit` blokuje commity zasahujúce do zmrazených ciest (zoznam v `.githooks/protected-paths.txt`). Aktivácia po klonovaní: `git config core.hooksPath .githooks`.
- `.claude/settings.json` zakazuje Claude Code upravovať tieto súbory.
- `.github/CODEOWNERS` vyžaduje review vlastníka.
