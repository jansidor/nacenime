# Lokálne kópie knižníc pre prototyp

`support.js` ich načítava odtiaľto namiesto z `unpkg.com`, aby návštevník neposielal IP adresu cudziemu CDN a web nezávisel od jeho dostupnosti. Súbory sú nezmenené balíky z npm a zhodujú sa s integritnými hashmi (SRI) v `support.js`.

| Súbor | Balík | Licencia |
|---|---|---|
| `react-18.3.1.production.min.js` | react@18.3.1 | MIT |
| `react-dom-18.3.1.production.min.js` | react-dom@18.3.1 | MIT |
| `babel-standalone-7.29.0.min.js` | @babel/standalone@7.29.0 | MIT |

Pri zmene verzie uprav adresy a hashe v `support.js` (`REACT_URL`, `REACT_DOM_URL`, `BABEL_URL`) aj v `<link rel="preload">` v hlavičke `Nacenime.dc.html`.

Babel sa na webe bežne nesťahuje: runtime ho načíta len pre importované súbory `.jsx`, ktoré kalkulačka nepoužíva.
