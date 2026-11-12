import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pripravCennik, odhad } from './odhad.js';

const c = pripravCennik(JSON.parse(readFileSync(new URL('../data/nacenime-cennik.json', import.meta.url), 'utf8')));
const pick = r => ({ stav: r.stav, zdroj: r.zdroj, uroven: r.uroven, pouzity_stav: r.pouzity_stav, pocet: r.pocet, od: r.od, do: r.do });

test('Byt, Ružomberok, Kompletná rekonštrukcia, 66 m²', () => {
  assert.deepEqual(pick(odhad(c, { typ: 'Byt', obec: 'Ružomberok', stav: 'Kompletná rekonštrukcia', vymera: 66 })),
    { stav: 'ok', zdroj: 'Predaje', uroven: 'obec', pouzity_stav: 'Kompletná rekonštrukcia', pocet: 6, od: 131000, do: 145000 });
});
test('Byt, Martin, Pôvodný, 60 m²', () => {
  const r = odhad(c, { typ: 'Byt', obec: 'Martin', stav: 'Pôvodný', vymera: 60 });
  assert.deepEqual([r.zdroj, r.pocet, r.od, r.do], ['Ponuky', 22, 111000, 132000]);
});
test('Apartmán, Demänovská Dolina, Novostavba, 40 m²', () => {
  const r = odhad(c, { typ: 'Apartmán', obec: 'Demänovská Dolina', stav: 'Novostavba', vymera: 40 });
  assert.deepEqual([r.zdroj, r.uroven, r.pocet, r.od, r.do], ['Ponuky', 'okres', 10, 137000, 169000]);
});
test('Pozemok, Liptovská Štiavnica, 800 m²', () => {
  const r = odhad(c, { typ: 'Pozemok', obec: 'Liptovská Štiavnica', vymera: 800 });
  assert.deepEqual([r.zdroj, r.uroven, r.pouzity_stav, r.pocet, r.od, r.do], ['Ponuky', 'okres', 'všetky', 17, 60000, 99000]);
  assert.match(r.veta, /Bez rozlíšenia stavu\. Rátané z celého okresu\.$/);
});
test('Chata, Dolný Kubín → málo dát', () => {
  const r = odhad(c, { typ: 'Chata', obec: 'Dolný Kubín', stav: 'Pôvodný', vymera: 50 });
  assert.equal(r.stav, 'málo dát');
  assert.equal(r.veta, 'Maklér vám povie cenu po obhliadke.');
});
test('Byt, Námestovo → málo dát', () => {
  assert.equal(odhad(c, { typ: 'Byt', obec: 'Námestovo', stav: 'Pôvodný', vymera: 50 }).stav, 'málo dát');
});
test('obec mimo zoznamu → mimo regiónu; bez diakritiky a veľkosti písmen sa nájde', () => {
  assert.equal(odhad(c, { typ: 'Byt', obec: 'Žilina', stav: 'Pôvodný', vymera: 50 }).stav, 'mimo regiónu');
  assert.equal(odhad(c, { typ: 'Byt', obec: 'ruzomberok', stav: 'Kompletná rekonštrukcia', vymera: 66 }).od, 131000);
});
test('typ vypnutý prepínačom → maklér', () => {
  const r = odhad(c, { typ: 'Byt', obec: 'Martin', stav: 'Pôvodný', vymera: 60 }, { typyBezRozpatia: ['Byt'] });
  assert.equal(r.stav, 'maklér');
  assert.equal(r.nadpis, 'Cenu vám povie maklér');
});
