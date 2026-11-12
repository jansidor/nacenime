<?php
declare(strict_types=1);
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'Leady'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Adresy, Api, Config, Leady, Odhad, Storage};

$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$nova = function (array $cfg = [], ?callable $http = null) use ($odhad): array {
    $config = new Config($cfg);
    $st = new Storage(':memory:');
    return [new Api($odhad, $config, $st, new Adresy($odhad, $config, $http)), $st];
};
$valid = ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => '66'];

[$api, $st] = $nova();
[$k, $r] = $api->handle('POST', '/api/odhad', $valid);
$check('odhad 200', [200, 'ok', 131000, 145000, 1], [$k, $r['stav'], $r['od'], $r['do'], $r['odhad_id']]);
$check('odhad poznámka', 'Vyššiu cenu dosahujú byty po rekonštrukcii a s dobrou prípravou predaja.', $r['poznamka']);
$check('desatinná čiarka', 200, $api->handle('POST', '/api/odhad', ['vymera' => '66,5'] + $valid)[0]);
foreach (['9', '2001', 'abc', '', '1e3'] as $v) {
    $check("výmera '$v' je neplatná", [422, 'Zadajte výmeru od 10 do 2000 m².'], [($x = $api->handle('POST', '/api/odhad', ['vymera' => $v] + $valid))[0], $x[1]['chyby']['vymera'] ?? null]);
}
$check('neznámy typ', 422, $api->handle('POST', '/api/odhad', ['typ' => 'Hrad'] + $valid)[0]);
$check('pozemok bez stavu', 200, $api->handle('POST', '/api/odhad', ['typ' => 'Pozemok', 'obec' => 'Martin', 'vymera' => 800])[0]);
$check('mimo regiónu sa neukladá', [200, 'mimo regiónu', false], [($x = $api->handle('POST', '/api/odhad', ['obec' => 'Žilina'] + $valid))[0], $x[1]['stav'], isset($x[1]['odhad_id'])]);
$check('GET na odhad = 405', 405, $api->handle('GET', '/api/odhad')[0]);
$check('neznáma cesta = 404', 404, $api->handle('GET', '/api/nic')[0]);

// kontakty
$check('pdf bez e-mailu', [422, 'Zadajte e-mail.'], [($x = $api->handle('POST', '/api/kontakt/pdf', ['email' => 'zle']))[0], $x[1]['chyby']['email']]);
$check('pdf ok, súhlas predvolene nie', [200, 0], [$api->handle('POST', '/api/kontakt/pdf', ['email' => 'a@b.sk', 'odhad_id' => 1])[0], $st->kontakty('pdf')[0]['suhlas']]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'c@d.sk', 'suhlas' => true]);
$check('pdf súhlas true', 1, $st->kontakty('pdf')[1]['suhlas']);
$check('pdf súhlas "true" (string) neplatí', 0, ($api->handle('POST', '/api/kontakt/pdf', ['email' => 'e@f.sk', 'suhlas' => 'true'])) ? $st->kontakty('pdf')[2]['suhlas'] : -1);
$check('neznámy odhad_id', 422, $api->handle('POST', '/api/kontakt/pdf', ['email' => 'a@b.sk', 'odhad_id' => 999])[0]);
$check('telefón < 9 číslic', 422, $api->handle('POST', '/api/kontakt/makler', ['telefon' => '0900 12'])[0]);
$check('telefón s písmenami', 422, $api->handle('POST', '/api/kontakt/makler', ['telefon' => '0900abc1234'])[0]);
$check('telefón ok', [200, 'Ďakujeme, ozveme sa vám.'], [($x = $api->handle('POST', '/api/kontakt/makler', ['telefon' => '+421 900 123 456', 'odhad_id' => 1]))[0], $x[1]['sprava']]);
$check('mimo regiónu e-mail', [200, 'Ďakujeme, dáme vám vedieť.'], [($x = $api->handle('POST', '/api/kontakt/mimo-regionu', ['email' => 'x@y.sk']))[0], $x[1]['sprava']]);
$check('honeypot: úspech, ale neuloží', [200, 0], [$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0900123456', 'web' => 'spam'])[0], count($st->kontakty('mimo_regionu')) - 1]);
$check('honeypot makler neuložený', 1, count($st->kontakty('makler')));

// SQL injection v texte sa uloží ako text
$api->handle('POST', '/api/odhad', ['obec' => "Martin'); DROP TABLE odhady;--"] + $valid);
$check('injection: tabuľka žije', 200, $api->handle('POST', '/api/odhad', $valid)[0]);

// rate limit
[$api2] = $nova(['NACENIME_RATE_LIMIT' => '3']);
$kody = [];
for ($i = 0; $i < 5; $i++) { $kody[] = $api2->handle('GET', '/api/obec', [], ['obec' => 'Martin'], '1.2.3.4')[0]; }
$check('rate limit: 4. požiadavka = 429', [200, 200, 200, 429, 429], $kody);
$check('rate limit: iná IP prejde', 200, $api2->handle('GET', '/api/obec', [], ['obec' => 'Martin'], '5.6.7.8')[0]);

// obec / adresa
$check('obec v regióne', [200, true, 'Ružomberok'], [($x = $api->handle('GET', '/api/obec', [], ['obec' => 'ruzomberok']))[0], $x[1]['v_regione'], $x[1]['obec']]);
$check('obec mimo', false, $api->handle('GET', '/api/obec', [], ['obec' => 'Žilina'])[1]['v_regione']);
$check('adresa < 3 znaky = bez hľadania', [200, []], [($x = $api->handle('GET', '/api/adresa', [], ['q' => 'By']))[0], $x[1]['navrhy']]);
$check('bez kľúča = záloha', true, $api->handle('GET', '/api/adresa', [], ['q' => 'Bystrická'])[1]['zaloha']);
$volania = [];
[$g] = $nova(['NACENIME_GOOGLE_API_KEY' => 'tajny'], function ($m, $u, $h, $b) use (&$volania) {
    $volania[] = [$m, $u, $h, $b];
    return [200, str_contains($u, 'autocomplete')
        ? json_encode(['suggestions' => [['placePrediction' => ['placeId' => 'ChIJabcdefghijk', 'structuredFormat' => ['mainText' => ['text' => 'Bystrická cesta 12'], 'secondaryText' => ['text' => 'Ružomberok']]]]]])
        : json_encode(['formattedAddress' => 'x', 'location' => ['latitude' => 49.07, 'longitude' => 19.3], 'addressComponents' => [['longText' => 'Žilina', 'types' => ['locality']]]])];
});
$x = $g->handle('GET', '/api/adresa', [], ['q' => 'Bystrická', 'token' => 'abcdefgh1234'])[1];
$body = json_decode($volania[0][3], true);
$check('návrhy z Google', 'Bystrická cesta 12', $x['navrhy'][0]['hlavny']);
$check('Google dostal parametre', ['sk', 'sk', 'abcdefgh1234', 'tajny'], [$body['regionCode'], $body['languageCode'], $body['sessionToken'], $volania[0][2]['X-Goog-Api-Key']]);
$check('kľúč nie je v odpovedi', false, str_contains(json_encode($x), 'tajny'));
$d = $g->handle('GET', '/api/adresa/detail', [], ['id' => 'ChIJabcdefghijk'])[1];
$check('detail: obec mimo regiónu', [false, 49.07], [$d['v_regione'], $d['lat']]);
$check('detail: neplatné id', 422, $g->handle('GET', '/api/adresa/detail', [], ['id' => '../x'])[0]);

// regresie z fuzz testu
$check('?obec[]=x nespôsobí chybu', 422, $api->handle('GET', '/api/obec', [], ['obec' => ['x']])[0]);
$check('?q[]=x je prázdne hľadanie', [200, []], [($x = $api->handle('GET', '/api/adresa', [], ['q' => ['x']]))[0], $x[1]['navrhy']]);
[$gz] = $nova(['NACENIME_GOOGLE_API_KEY' => 'k'], fn() => [200, '{"suggestions":"x"}']);
$check('Google vráti nezmysel = bez výnimky', [200, []], [($x = $gz->handle('GET', '/api/adresa', [], ['q' => 'Bystrická']))[0], $x[1]['navrhy']]);
[$gz2] = $nova(['NACENIME_GOOGLE_API_KEY' => 'k'], fn() => [200, '{"location":{"latitude":"a","longitude":null},"addressComponents":"x"}']);
$check('detail s nezmyslom = záloha', true, $gz2->handle('GET', '/api/adresa/detail', [], ['id' => 'ChIJabcdefghijk'])[1]['zaloha'] ?? false);

// health
$check('health ok', [200, ['stav' => 'ok']], $api->handle('GET', '/api/health'));
$check('health POST = 405', 405, $api->handle('POST', '/api/health')[0]);
[$apiL] = $nova(['NACENIME_RATE_LIMIT' => '1']);
$apiL->handle('GET', '/api/obec', [], ['obec' => 'Martin'], '9.9.9.9');
$apiL->handle('GET', '/api/obec', [], ['obec' => 'Martin'], '9.9.9.9');
$check('health nie je limitovaný', 200, $apiL->handle('GET', '/api/health', [], [], '9.9.9.9')[0]);

// leady a CSV
[$gl, $sl] = $nova();
$gl->handle('POST', '/api/odhad', ['obec' => 'Martin', 'stav' => 'Pôvodný'] + $valid);
$gl->handle('POST', '/api/kontakt/makler', ['telefon' => '+421 900 123 456', 'odhad_id' => 1]);
$gl->handle('POST', '/api/kontakt/pdf', ['email' => 'bez@suhlasu.sk', 'odhad_id' => 1]);
$gl->handle('POST', '/api/kontakt/pdf', ['email' => '=HYPERLINK("http://zle")@x.sk', 'suhlas' => true]); // neplatný e-mail
$gl->handle('POST', '/api/kontakt/pdf', ['email' => 'so@suhlasom.sk', 'suhlas' => true, 'odhad_id' => 1]);
$check('leady: makler + pdf so súhlasom', ['makler', 'pdf'], array_column($sl->leady(), 'druh'));
$check('leady od dátumu v budúcnosti', [], $sl->leady('2999-01-01'));
$check('CSV neutralizuje vzorce', ["'=SUM(A1)", "'+421900", "'-1", "'@x", 'bežný', ''], array_map([Leady::class, 'bezpecne'], ['=SUM(A1)', '+421900', '-1', '@x', 'bežný', '']));
$csv = Leady::csv($sl->leady());
$check('CSV má BOM, hlavičku a 2 riadky', [true, 3], [str_starts_with($csv, "\xEF\xBB\xBFid;vytvorene"), count(array_filter(explode("\n", $csv)))]);
$check('CSV obsahuje telefón ako text', true, str_contains($csv, "'+421 900 123 456"));

// GDPR
[$g2, $s2] = $nova();
$g2->handle('POST', '/api/odhad', $valid);
$g2->handle('POST', '/api/kontakt/pdf', ['email' => 'jozo@example.sk', 'odhad_id' => 1]);
$g2->handle('POST', '/api/kontakt/makler', ['telefon' => '0900123456', 'odhad_id' => 1]);
$check('export podľa e-mailu', ['jozo@example.sk'], array_column($s2->najdiKontakty('jozo@example.sk'), 'email'));
$check('zmazanie e-mailu', 1, $s2->zmazKontakty('jozo@example.sk'));
$check('telefón ostal (odhad ešte potrebný)', 1, count($s2->najdiKontakty('0900123456')));
$check('zmazanie telefónu', 1, $s2->zmazKontakty('0900123456'));
$check('odhad zmazaného kontaktu je preč', false, $s2->odhadExistuje(1));
$g2->handle('POST', '/api/odhad', $valid); // cudzí odhad bez kontaktu
$check('cudzí odhad ostáva', true, $s2->odhadExistuje(2));
$check('po zmazaní nič', [], $s2->najdiKontakty('0900123456'));
$g2->handle('POST', '/api/kontakt/mimo-regionu', ['email' => 'stary@example.sk']);
$check('retencia: čerstvé ostáva', ['kontakty' => 0, 'odhady' => 0], $s2->zmazStarsie(24));
$check('retencia: staré ide preč', 1, $s2->zmazStarsie(24, strtotime('+30 months'))['kontakty']);

exit($fail > 0 ? 1 : 0);
