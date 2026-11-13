#!/usr/bin/env bash
# Zostaví strom na nasadenie z Gitu (vetva `deploy`): zdrojové súbory bez testov a vývojárskych vecí + backend/vendor
# + stub pre /api. Použitie: deploy/zostav-deploy-strom.sh <cieľový prázdny priečinok> [--bez-vendor]
set -euo pipefail
koren="$(cd "$(dirname "$0")/.." && pwd)"
ciel="${1:?Zadajte cieľový priečinok}"
mkdir -p "$ciel"
[ -z "$(ls -A "$ciel")" ] || { echo "Cieľový priečinok musí byť prázdny: $ciel" >&2; exit 1; }

cd "$koren"
# Len sledované súbory; bez testov, CI, hookov, pokynov pre Claude, Dockeru a pomocných skriptov.
git archive --format=tar HEAD -- . \
  ':(exclude)backend/tests' ':(exclude).github' ':(exclude).githooks' ':(exclude).claude' ':(exclude)CLAUDE.md' \
  ':(exclude)deploy' ':(exclude).gitignore' ':(exclude)*/test.cjs' ':(exclude)*.test.js' | tar -x -C "$ciel"

if [ "${2:-}" != "--bez-vendor" ]; then
  (cd "$ciel/backend" && composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-plugins >/dev/null)
  find "$ciel/backend/vendor" -name .git -type d -prune -exec rm -rf {} +
fi

cp "$koren/deploy/hosting/api.php" "$ciel/design_handoff_nacenime/api.php"
cp "$koren/deploy/hosting/htaccess" "$ciel/design_handoff_nacenime/.htaccess"
printf 'zdroj: %s\nzostavené: %s\n' "$(git rev-parse HEAD)" "$(date -u +%FT%TZ)" > "$ciel/DEPLOY.txt"
echo "Hotovo: $ciel ($(find "$ciel" -type f | wc -l) súborov)"
