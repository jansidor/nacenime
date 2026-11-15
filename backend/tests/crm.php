<?php
declare(strict_types=1);
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'CrmStorage', 'Crm'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Api, Config, Crm, CrmStorage, Odhad, Storage};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$st = new Storage(':memory:');
$api = new Api($odhad, new Config([]), $st);
$config = new Config(['NACENIME_CRM_ADMIN_EMAIL' => 'Jan@NajBy.sk', 'NACENIME_CRM_ADMIN_MENO' => 'Ján Sidor', 'NACENIME_CRM_URL' => 'https://crm.test']);
$posta = [];
$crm = new Crm(new CrmStorage($st), $st, $config, function (string $komu, string $predmet, string $telo) use (&$posta): void { $posta[] = compact('komu', 'predmet', 'telo'); });
$h = fn(string $m, string $p, array $b = [], string $t = '') => $crm->handle($m, $p, $b, $t, '');
$token = fn(string $telo) => preg_match('/#heslo=([A-Za-z0-9_-]+)/', $telo, $m) ? $m[1] : '';

// leady z kalkulačky
$o1 = $api->handle('POST', '/api/odhad', ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66, 'adresa' => ['ulica' => 'Podhora 48', 'psc' => '034 01']])[1]['odhad_id'];
$o2 = $api->handle('POST', '/api/odhad', ['typ' => 'Dom', 'obec' => 'Martin', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 120])[1]['odhad_id'];
$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0905 111 222', 'odhad_id' => $o1]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'so@suhlasom.sk', 'suhlas' => true, 'odhad_id' => $o2]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'bez@suhlasu.sk', 'odhad_id' => $o2]);

// prihlásenie a prvý správca
$check('bez prihlásenia 401', 401, $h('GET', '/api/crm/data')[0]);
$check('neznámy účet sa neprihlási', 422, $h('POST', '/api/crm/prihlasenie', ['email' => 'x@y.sk', 'heslo' => 'nieco-dlhe-12'])[0]);
$r = $h('POST', '/api/crm/odkaz', ['email' => 'nikto@nikde.sk']);
$check('odkaz pre neznámy e-mail: rovnaká odpoveď, žiadny e-mail', [200, 0], [$r[0], count($posta)]);
$r = $h('POST', '/api/crm/odkaz', ['email' => 'jan@najby.sk']);
$check('prvý správca dostal odkaz', [200, 'jan@najby.sk', true], [$r[0], $posta[0]['komu'] ?? '', str_contains($posta[0]['telo'] ?? '', 'https://crm.test/#heslo=')]);
$t = $token($posta[0]['telo']);
$check('krátke heslo (7 znakov) 422', 422, $h('POST', '/api/crm/heslo', ['token' => $t, 'heslo' => 'kratke7'])[0]);
$r = $h('POST', '/api/crm/heslo', ['token' => $t, 'heslo' => 'spravne-heslo-123']);
$check('heslo nastavené, prihlásený (cookie)', [200, true], [$r[0], strlen((string) ($r[2][0] ?? '')) > 30]);
$adm = $r[2][0];
$check('odkaz sa nedá použiť druhýkrát', 422, $h('POST', '/api/crm/heslo', ['token' => $t, 'heslo' => 'ine-heslo-12345'])[0]);
$r = $h('POST', '/api/crm/prihlasenie', ['email' => 'JAN@najby.sk', 'heslo' => 'spravne-heslo-123']);
$check('prihlásenie heslom', [200, true], [$r[0], isset($r[2][0])]);
$check('zlé heslo', 422, $h('POST', '/api/crm/prihlasenie', ['email' => 'jan@najby.sk', 'heslo' => 'zle-heslo-12345'])[0]);
$posta = [];
$h('POST', '/api/crm/odkaz', ['email' => 'druhy@admin.sk']);
$check('ďalší správca sa sám nezaloží', 0, count($posta));

// dáta
$d = $h('GET', '/api/crm/data', [], $adm)[1];
$check('správca vidí 2 leady (telefonát, PDF so súhlasom)', [2, 'Správca', 'Ján Sidor'], [count($d['leads']), $d['user']['rola'], $d['user']['meno']]);
$tel = array_values(array_filter($d['leads'], fn($l) => $l['telefon'] === '0905 111 222'))[0];
$pdf = array_values(array_filter($d['leads'], fn($l) => $l['email'] === 'so@suhlasom.sk'))[0];
$check('lead má údaje z kalkulačky', ['Nový', 'Ružomberok', 'byt', 66, 'Podhora 48, 034 01', true, 'lead prijatý z kalkulačky'], [$tel['stav'], $tel['lokalita'], $tel['typ'], $tel['vymera'], $tel['adresa'], $tel['suhlas'], $tel['history'][0]['text']]);
$check('PDF lead: nevolať, odhad v tisícoch', [false, 'dom'], [$pdf['suhlas'], $pdf['typ']]);
$check('číslo leadu', 1, preg_match('/^LD-\d{4}-\d{4}$/', $tel['cislo']));

// pozvánka makléra
$posta = [];
$r = $h('POST', '/api/crm/clen', ['akcia' => 'pozvat', 'meno' => 'Peter Hudec', 'email' => 'peter@najby.sk'], $adm);
$check('pozvánka', [200, 'peter@najby.sk', 2], [$r[0], $posta[0]['komu'] ?? '', count($r[1]['team'])]);
$check('duplicitný e-mail', 422, $h('POST', '/api/crm/clen', ['akcia' => 'pozvat', 'meno' => 'X', 'email' => 'peter@najby.sk'], $adm)[0]);
$mak = $h('POST', '/api/crm/heslo', ['token' => $token($posta[0]['telo']), 'heslo' => 'heslo-makler-12'])[2][0];
$pid = array_values(array_filter($r[1]['team'], fn($m) => $m['rola'] === 'Maklér'))[0]['id'];
$check('maklér nevidí nepridelené leady', 0, count($h('GET', '/api/crm/data', [], $mak)[1]['leads']));
$check('maklér nemôže na cudzí lead', 404, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'poznamka', 'text' => 'x'], $mak)[0]);
$check('maklér nemôže pozývať', 403, $h('POST', '/api/crm/clen', ['akcia' => 'pozvat', 'meno' => 'X', 'email' => 'x@x.sk'], $mak)[0]);

// pravidlá stavov a pridelenie
$check('bez makléra nemožno ďalej', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Kontaktovaný'], $adm)[0]);
$posta = [];
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'priradit', 'komu' => $pid], $adm);
$check('pridelenie posunie na Pridelený a pošle e-mail', ['Pridelený', $pid, 'peter@najby.sk', true], [$r[1]['lead']['stav'], $r[1]['lead']['pridelene'], $posta[0]['komu'] ?? '', str_contains($posta[0]['telo'] ?? '', '0905 111 222')]);
$check('maklér vidí pridelený lead', 1, count($h('GET', '/api/crm/data', [], $mak)[1]['leads']));
$check('maklér nemôže prideľovať', 403, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'priradit', 'komu' => ''], $mak)[0]);
$check('lead s maklérom nemôže byť Nový', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Nový'], $mak)[0]);
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Kontaktovaný'], $mak);
$check('maklér posunie stav, zapíše prvý kontakt', ['Kontaktovaný', 2, true], [$r[1]['lead']['stav'], $r[1]['lead']['max'], $r[1]['lead']['firstContact'] !== null]);
$check('Stratené bez dôvodu', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Stratené'], $mak)[0]);
$check('Stratené „iné“ bez textu', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Stratené', 'dovod' => 'iné'], $mak)[0]);
$check('zrušiť pridelenie v riešení nejde', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'priradit', 'komu' => ''], $adm)[0]);

// krok, poznámka, pripomienka
$check('krok bez času', 422, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'krok', 'text' => 'Zavolať'], $mak)[0]);
$at = CrmStorage::teraz() + 3600_000;
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'krok', 'text' => 'Zavolať s návrhom ceny', 'at' => $at], $mak);
$check('krok uložený', ['Zavolať s návrhom ceny', $at], [$r[1]['lead']['krokText'], $r[1]['lead']['krokAt']]);
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'poznamka', 'text' => 'Zdvihne po 17. hodine.'], $mak);
$check('poznámka', ['Zdvihne po 17. hodine.', 'Peter Hudec'], [$r[1]['lead']['notes'][0]['text'] ?? '', $r[1]['lead']['notes'][0]['who'] ?? '']);
$posta = [];
$check('pripomienka pred termínom nejde', ['odoslane' => 0, 'zlyhalo' => 0], $crm->pripomienky(CrmStorage::teraz()));
$check('pripomienka v čase kroku ide maklérovi', [['odoslane' => 1, 'zlyhalo' => 0], 'peter@najby.sk'], [$crm->pripomienky($at + 1000), $posta[0]['komu'] ?? '']);
$check('pripomienka len raz', 0, $crm->pripomienky($at + 2000)['odoslane']);
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'meno', 'meno' => 'Jana Nová'], $mak);
$check('meno klienta', 'Jana Nová', $r[1]['lead']['meno']);

// predaj
$h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'stav', 'stav' => 'Predané'], $mak);
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'predaj', 'cena' => '138000', 'datum' => '2026-12-01'], $mak);
$check('predaj', ['Predané', 138000, '2026-12-01'], [$r[1]['lead']['stav'], $r[1]['lead']['cena'], $r[1]['lead']['datum']]);

$check('maklér nemôže mazať', 403, $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'vymazat'], $mak)[0]);

// odstránenie makléra s aktívnym leadom
$posta = [];
$h('POST', '/api/crm/clen', ['akcia' => 'pozvat', 'meno' => 'Mária Šimková', 'email' => 'maria@najby.sk'], $adm);
$team = $h('GET', '/api/crm/data', [], $adm)[1]['team'];
$mid = array_values(array_filter($team, fn($m) => $m['meno'] === 'Mária Šimková'))[0]['id'];
$h('POST', '/api/crm/lead', ['id' => $pdf['id'], 'akcia' => 'priradit', 'komu' => $pid], $adm);
$check('odstránenie bez prehodenia aktívnych leadov', 422, $h('POST', '/api/crm/clen', ['akcia' => 'odstranit', 'id' => $pid], $adm)[0]);
$r = $h('POST', '/api/crm/clen', ['akcia' => 'odstranit', 'id' => $pid, 'komu' => $mid], $adm);
$check('odstránený, leady prehodené', [200, $mid], [$r[0], array_values(array_filter($r[1]['leads'], fn($l) => $l['id'] === $pdf['id']))[0]['pridelene']]);
$check('odstránený maklér stratí reláciu', 401, $h('GET', '/api/crm/data', [], $mak)[0]);

// vymazanie údajov a odhlásenie
$r = $h('POST', '/api/crm/lead', ['id' => $tel['id'], 'akcia' => 'vymazat'], $adm);
$check('vymazanie údajov klienta', ['Vymazaný klient', '–', '–', [], 'údaje klienta vymazané'], [$r[1]['lead']['meno'], $r[1]['lead']['telefon'], $r[1]['lead']['adresa'], $r[1]['lead']['notes'], end($r[1]['lead']['history'])['text']]);
$check('audit obsahuje vymazanie', true, (bool) array_filter($h('GET', '/api/crm/data', [], $adm)[1]['audit'], fn($a) => str_contains($a['text'], 'vymazal údaje')));
$check('po vymazaní nie je meno klienta ani v histórii', false, (bool) array_filter($r[1]['lead']['history'], fn($x) => str_contains($x['text'], 'Jana Nová')));
$check('história ostane s udalosťou bez mena', true, (bool) array_filter($r[1]['lead']['history'], fn($x) => $x['text'] === 'meno klienta: (vymazané)'));

// nové heslo cez odkaz odhlási staré relácie
$posta = [];
$h('POST', '/api/crm/odkaz', ['email' => 'jan@najby.sk']);
$r = $h('POST', '/api/crm/heslo', ['token' => $token($posta[0]['telo'] ?? ''), 'heslo' => 'uplne-nove-heslo-1']);
$check('nové heslo: stará relácia neplatí, nová áno', [200, 401, 200], [$r[0], $h('GET', '/api/crm/data', [], $adm)[0], $h('GET', '/api/crm/data', [], $r[2][0])[0]]);
$adm = $r[2][0];

// GDPR zmazanie kontaktu (bin/gdpr.php zmaz) zmaže aj CRM záznamy: meno, poznámky, históriu
$kid = (int) preg_replace('/\D/', '', $pdf['id']);
$h('POST', '/api/crm/lead', ['id' => $pdf['id'], 'akcia' => 'poznamka', 'text' => 'volať po 17:00'], $adm);
$st->zmazKontakty('so@suhlasom.sk');
$zvysky = array_sum(array_map(fn($t) => (int) $st->pdo()->query("SELECT COUNT(*) FROM $t WHERE kontakt_id = $kid")->fetchColumn(), ['crm_leady', 'crm_poznamky', 'crm_historia']));
$check('GDPR zmazanie upratá CRM (lead, poznámky, história)', 0, $zvysky);
$check('zmazaný lead nie je v CRM', false, (bool) array_filter($h('GET', '/api/crm/data', [], $adm)[1]['leads'], fn($l) => $l['id'] === $pdf['id']));

$h('POST', '/api/crm/odhlasenie', [], $adm);
$check('po odhlásení 401', 401, $h('GET', '/api/crm/data', [], $adm)[0]);

// limit pokusov na účet (aj bez IP, napr. veľa rôznych IP)
$kody = array_map(fn() => $h('POST', '/api/crm/prihlasenie', ['email' => 'Hadany@Ucet.sk', 'heslo' => 'zle-heslo-123456'])[0], range(1, 6));
$check('6. pokus na ten istý účet za minútu: 429', [422, 422, 422, 422, 422, 429], $kody);
$check('iný účet limit nezdieľa', 422, $h('POST', '/api/crm/prihlasenie', ['email' => 'iny@ucet.sk', 'heslo' => 'zle-heslo-123456'])[0]);

exit($fail > 0 ? 1 : 0);
