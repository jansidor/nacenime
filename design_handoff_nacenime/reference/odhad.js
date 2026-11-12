const norm = t => String(t ?? '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const round1000 = n => Math.max(0, Math.round(n / 1000) * 1000);

export function pripravCennik(data) {
  return {
    skupiny: new Map(data.cennik.map(g => [g.kluc, g])),
    lokality: new Map(Object.entries(data.lokality).map(([obec, okres]) => [norm(obec), { obec, okres }])),
    stavy: [...new Set(data.cennik.map(g => g.stav))].filter(s => s !== 'všetky'),
    typy: Object.keys(data.zlavy),
    prahy: data.prahy,
    zlavy: data.zlavy
  };
}

const poradie = stav => {
  const sk = s => [['Predaje', 'obec', s], ['Ponuky', 'obec', s], ['Predaje', 'okres', s], ['Ponuky', 'okres', s]];
  return [...(stav ? sk(stav) : []), ...sk('všetky')];
};

const prazdny = { od: null, do: null, pocet: null, zdroj: null, uroven: null, pouzity_stav: null, veta: null, nadpis: null };

export function odhad(c, { typ, obec, stav, vymera }, { typyBezRozpatia = [] } = {}) {
  if (typyBezRozpatia.includes(typ)) return { stav: 'maklér', ...prazdny, nadpis: 'Cenu vám povie maklér', veta: 'Pri tomto type nehnuteľnosti rozpätie neukazujeme. Maklér si ju pozrie a povie vám cenu.' };
  const lok = c.lokality.get(norm(obec));
  if (!lok) return { stav: 'mimo regiónu', ...prazdny };
  const lokalita = { obec: lok.obec, okres: 'okres ' + lok.okres };
  for (const [zdroj, uroven, s] of poradie(stav)) {
    const g = c.skupiny.get([zdroj, typ, lokalita[uroven], s].join('|'));
    if (!g || g.pocet < c.prahy[zdroj.toLowerCase()]) continue;
    const [zlavaMin, zlavaMax] = c.zlavy[typ];
    const predaj = zdroj === 'Predaje';
    const veta = [
      predaj ? `Podľa ${g.pocet} podobných predajov v okolí.` : `Podľa ${g.pocet} aktuálnych ponúk v okolí, po odpočítaní bežnej zľavy pri predaji.`,
      s === 'všetky' && 'Bez rozlíšenia stavu.',
      uroven === 'okres' && 'Rátané z celého okresu.'
    ].filter(Boolean).join(' ');
    return {
      stav: 'ok',
      od: round1000(g.q1 * vymera - (predaj ? 0 : zlavaMax)),
      do: round1000(g.q3 * vymera - (predaj ? 0 : zlavaMin)),
      pocet: g.pocet, zdroj, uroven, pouzity_stav: s, veta, nadpis: null
    };
  }
  return { stav: 'málo dát', ...prazdny, veta: 'Maklér vám povie cenu po obhliadke.', nadpis: 'Na odhad tu máme málo predajov' };
}
