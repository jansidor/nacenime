<?php
declare(strict_types=1);
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Diagnostika', 'Zaloha'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Config, Diagnostika, Storage, Zaloha};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$tmp = sys_get_temp_dir() . '/nacenime-prev-' . bin2hex(random_bytes(4));
mkdir($tmp);
$root = $tmp . '/backend';
mkdir("$root/var", 0777, true); mkdir("$root/public");
copy(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json', "$tmp/cennik.json");
$rozsirenia = ['mbstring', 'pdo_sqlite', 'curl', 'dom', 'gd', 'intl'];
$hladaj = fn(array $v, string $text, string $uroven) => count(array_filter($v, fn($r) => $r[0] === $uroven && str_contains($r[1], $text)));

$zakl = ['NACENIME_CENNIK_PATH' => "$tmp/cennik.json", 'NACENIME_DB_PATH' => "$root/var/n.sqlite"];
$v = Diagnostika::skontroluj(new Config($zakl), $root, $rozsirenia, '8.3.0');
$check('základ: žiadna chyba', 0, count(array_filter($v, fn($r) => $r[0] === 'CHYBA')));
$check('chýba Google kľúč a SMTP = varovanie', [1, 1], [$hladaj($v, 'Google API kľúč nie je', 'VAROVANIE'), $hladaj($v, 'SMTP nie je', 'VAROVANIE')]);
$check('zástupné meno makléra = varovanie', 1, $hladaj($v, 'meno makléra', 'VAROVANIE'));

$v = Diagnostika::skontroluj(new Config($zakl), $root, ['mbstring'], '7.4.0');
$check('staré PHP a chýbajúce rozšírenia = chyby', [1, 4], [$hladaj($v, 'príliš staré', 'CHYBA'), $hladaj($v, 'chýba rozšírenie', 'CHYBA')]);
$v = Diagnostika::skontroluj(new Config(['NACENIME_CENNIK_PATH' => "$tmp/nie-je.json"] + $zakl), $root, $rozsirenia, '8.3.0');
$check('chýbajúci cenník = chyba', 1, $hladaj($v, 'cenník sa nedá čítať', 'CHYBA'));
file_put_contents("$tmp/zly.json", '{"x":1}');
$v = Diagnostika::skontroluj(new Config(['NACENIME_CENNIK_PATH' => "$tmp/zly.json"] + $zakl), $root, $rozsirenia, '8.3.0');
$check('neúplný cenník = chyba', 1, $hladaj($v, 'nie je platný', 'CHYBA'));
copy("$tmp/cennik.json", "$root/public/cennik.json");
$v = Diagnostika::skontroluj(new Config(['NACENIME_CENNIK_PATH' => "$root/public/cennik.json"] + $zakl), $root, $rozsirenia, '8.3.0');
$check('cenník vo verejnom priečinku = chyba', 1, $hladaj($v, 'vo verejnom priečinku', 'CHYBA'));
$v = Diagnostika::skontroluj(new Config(['NACENIME_DB_PATH' => "$root/public/x.sqlite"] + $zakl), $root, $rozsirenia, '8.3.0');
$check('databáza vo verejnom priečinku = chyba', 1, $hladaj($v, 'databáza je vo verejnom', 'CHYBA'));
$v = Diagnostika::skontroluj(new Config(['NACENIME_RATE_LIMIT' => 'abc', 'NACENIME_SMTP_HOST' => 'h', 'NACENIME_SMTP_USER' => 'u', 'NACENIME_SMTP_SECURE' => 'zle', 'NACENIME_MAIL_FROM' => 'nie-email'] + $zakl), $root, $rozsirenia, '8.3.0');
$check('zlé hodnoty = chyby', [1, 1, 1], [$hladaj($v, 'RATE_LIMIT', 'CHYBA'), $hladaj($v, 'SMTP_SECURE', 'CHYBA'), $hladaj($v, 'MAIL_FROM', 'CHYBA')]);
$check('SMTP používateľ bez hesla = varovanie', 1, $hladaj($v, 'heslo chýba', 'VAROVANIE'));
$tajne = Diagnostika::skontroluj(new Config(['NACENIME_GOOGLE_API_KEY' => 'SUPERTAJNY', 'NACENIME_SMTP_HOST' => 'h', 'NACENIME_SMTP_USER' => 'u', 'NACENIME_SMTP_PASS' => 'HESLOTAJNE'] + $zakl), $root, $rozsirenia, '8.3.0');
$check('výpis neobsahuje tajomstvá', false, str_contains(json_encode($tajne, JSON_UNESCAPED_UNICODE), 'TAJN'));

// záloha
$st = new Storage("$root/var/n.sqlite");
$st->ulozKontakt('mimo_regionu', 'a@b.sk', null, false, null);
$z1 = Zaloha::vytvor("$root/var/n.sqlite", "$tmp/zalohy", strtotime('2026-01-01 10:00:00'));
$check('záloha má práva 0600', '0600', substr(sprintf('%o', fileperms($z1)), -4));
$check('záloha obsahuje dáta', ['a@b.sk'], array_column((new Storage($z1))->kontakty('mimo_regionu'), 'email'));
foreach (['2026-01-02', '2026-01-03', '2026-01-04'] as $d) { Zaloha::vytvor("$root/var/n.sqlite", "$tmp/zalohy", strtotime("$d 10:00:00")); }
$check('rotácia ponechá 2 najnovšie', [2, 2], [count(Zaloha::rotuj("$tmp/zalohy", 2)), count(glob("$tmp/zalohy/*.sqlite"))]);
$check('najstaršie sú preč', false, file_exists($z1));
$check('rotácia nezmaže poslednú zálohu ani pri 0', [1, 1], [count(Zaloha::rotuj("$tmp/zalohy", 0)), count(glob("$tmp/zalohy/*.sqlite"))]);
$check('chýbajúca databáza = výnimka', true, (function () use ($tmp) { try { Zaloha::vytvor("$tmp/nie.sqlite", "$tmp/z2"); return false; } catch (RuntimeException) { return true; } })());

// upratanie
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($tmp);
exit($fail > 0 ? 1 : 0);
