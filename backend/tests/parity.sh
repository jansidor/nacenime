#!/usr/bin/env bash
# Porovná PHP port s JS referenciou na všetkých kombináciách vstupov.
set -euo pipefail
cd "$(dirname "$0")"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
node --input-type=module -e "
import { readFileSync, writeFileSync } from 'node:fs';
import { pripravCennik, odhad } from '../../design_handoff_nacenime/reference/odhad.js';
const d = JSON.parse(readFileSync('../../design_handoff_nacenime/data/nacenime-cennik.json','utf8'));
const c = pripravCennik(d);
const obce = [...Object.keys(d.lokality), 'Žilina', 'ruzomberok'];
const stavy = [...c.stavy, null];
const out = [];
for (const typ of c.typy) for (const obec of obce) for (const stav of stavy) for (const vymera of [10, 33.5, 66, 120, 800, 2000])
  for (const bez of [[], [typ]]) out.push({ in: { typ, obec, stav, vymera }, bez, out: odhad(c, { typ, obec, stav, vymera }, { typyBezRozpatia: bez }) });
writeFileSync('$tmp/js.json', JSON.stringify(out));
"
php -r '
require "../src/Odhad.php";
$o = Nacenime\Odhad::zo_suboru("../../design_handoff_nacenime/data/nacenime-cennik.json");
$cases = json_decode(file_get_contents($argv[1]), true); $bad = 0;
foreach ($cases as $c) {
  $r = $o->odhad($c["in"], $c["bez"]);
  if (json_encode($r) !== json_encode($c["out"])) { if ($bad++ < 5) { echo "ROZDIEL ".json_encode($c["in"], JSON_UNESCAPED_UNICODE)."\n JS:  ".json_encode($c["out"], JSON_UNESCAPED_UNICODE)."\n PHP: ".json_encode($r, JSON_UNESCAPED_UNICODE)."\n"; } }
}
echo count($cases)." prípadov, rozdielov: $bad\n"; exit($bad ? 1 : 0);
' "$tmp/js.json"
