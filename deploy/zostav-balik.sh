#!/usr/bin/env bash
# Zostaví ZIP na nahratie na zdieľaný hosting (PHP 8.1). Použitie: deploy/zostav-balik.sh
# Výsledok: dist/nacenime-balik-<dátum>.zip s priečinkami app/ (mimo verejného) a www/ (verejný).
# Do balíka sa NEDOSTANE: .env, databáza, testy, dev závislosti, git.
set -euo pipefail
koren="$(cd "$(dirname "$0")/.." && pwd)"
stage="$(mktemp -d)"; trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/app" "$stage/www" "$koren/dist"

cp -r "$koren/backend/src" "$koren/backend/public" "$koren/backend/bin" "$stage/app/"
cp "$koren/backend/composer.json" "$koren/backend/composer.lock" "$stage/app/"
mkdir -p "$stage/app/data" "$stage/app/var"
cp "$koren/design_handoff_nacenime/data/nacenime-cennik.json" "$stage/app/data/"
touch "$stage/app/var/.gitkeep"
# závislosti bez dev balíkov (platforma 8.1 je v composer.json)
(cd "$stage/app" && composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-plugins >/dev/null 2>&1)
rm -f "$stage/app/composer.json" "$stage/app/composer.lock"

# konfigurácia: šablóna s relatívnymi cestami (relatívne ku koreňu app/)
sed -e 's#^NACENIME_CENNIK_PATH=.*#NACENIME_CENNIK_PATH=data/nacenime-cennik.json#' \
    -e 's#^NACENIME_DB_PATH=.*#NACENIME_DB_PATH=var/nacenime.sqlite#' \
    "$koren/backend/.env.example" > "$stage/app/.env.example"

cp "$koren/deploy/hosting/api.php" "$koren/deploy/hosting/.htaccess" "$stage/www/"
cp "$koren/deploy/HOSTCREATORS.md" "$stage/NAVOD.md"

zip="$koren/dist/nacenime-balik-$(date +%Y%m%d).zip"
rm -f "$zip"
find "$stage" -name .git -type d -prune -exec rm -rf {} +   # git metadáta knižníc na hosting nepatria
(cd "$stage" && zip -qr "$zip" . -x '*.DS_Store')
echo "Hotovo: $zip ($(du -h "$zip" | cut -f1))"
