// Cookie súhlas pre nacenime.sk. Žiadny sledovací skript sa nenačíta pred súhlasom.
//
// Čo robí:
//  - Pri prvej návšteve ukáže lištu. Bez rozhodnutia sa NESPUSTÍ nič: ani Google Tag Manager, ani Microsoft Clarity.
//  - „Prijať všetko“ a „Odmietnuť“ sú rovnocenné (rovnaký vzhľad aj dôraz). „Nastavenia“ dovoľuje vybrať kategórie.
//  - Voľba sa uloží do nevyhnutnej cookie `nm_cookies` na 6 mesiacov, potom sa lišta ukáže znova.
//  - Po analytickom súhlase sa najprv nastaví Consent Mode v2 (predvolene všetko zamietnuté, povolí sa len analytics_storage)
//    a až potom sa načíta Google Tag Manager a Clarity.
//  - Súhlas sa dá kedykoľvek odvolať cez odkaz „Cookies“ v pätičke (alebo window.NacenimeCookies.otvor()).
//    Odvolanie zmaže analytické cookies a stránku načíta znova, aby skripty prestali bežať.
//  - V iframe (náhľady dizajnu) sa lišta ani sledovanie nespúšťa.
//
// Nastavenie: zmeň GTM_ID a CLARITY_ID nižšie. Ak niektoré ID nechceš používať, nastav ho na ''.
(function () {
  'use strict';

  var GTM_ID = 'GTM-WHXQF3H7';
  var CLARITY_ID = 'zbymfki8xh';
  var COOKIE = 'nm_cookies';
  var PLATNOST_SEKUND = 60 * 60 * 24 * 183; // 6 mesiacov
  var VERZIA = 1;                           // zvýšiť pri zmene účelov, aby sa súhlas vypýtal znova
  // Stránky so zásadami (vedľa Nacenime.dc.html, relatívne k aktuálnej stránke)
  var ODKAZ_COOKIES = './cookies.html';
  var ODKAZ_OSOBNE_UDAJE = './ochrana-osobnych-udajov.html';

  if (window.top !== window.self) return;   // náhľady v iframe: bez lišty a bez sledovania

  // ---------- uloženie voľby ----------
  function precitaj() {
    var m = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
    if (!m) return null;
    var p = decodeURIComponent(m[1]).split('.'); // v.analytika.cas
    var v = parseInt(p[0], 10), a = p[1], t = parseInt(p[2], 10);
    if (v !== VERZIA || (a !== '0' && a !== '1') || !t) return null;
    if (Date.now() / 1000 - t > PLATNOST_SEKUND) return null;  // vypršalo
    return { analytika: a === '1', cas: t };
  }
  function uloz(analytika) {
    var hodnota = VERZIA + '.' + (analytika ? 1 : 0) + '.' + Math.floor(Date.now() / 1000);
    document.cookie = COOKIE + '=' + encodeURIComponent(hodnota) + '; Max-Age=' + PLATNOST_SEKUND +
      '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  // ---------- načítanie sledovania (len po súhlase) ----------
  var nacitane = false;
  function gtag() { window.dataLayer.push(arguments); }

  function nacitajSledovanie() {
    if (nacitane) return;
    nacitane = true;
    if (GTM_ID) {
      window.dataLayer = window.dataLayer || [];
      // Consent Mode v2: najprv predvolene zamietnuté, potom povolená len analytika. Reklamné účely sa nepoužívajú.
      gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', wait_for_update: 500 });
      gtag('consent', 'update', { analytics_storage: 'granted' });
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      var s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(GTM_ID);
      document.head.appendChild(s);
    }
    if (CLARITY_ID) {
      (function (c, l, a, r, i, t, y) {
        c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
        t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
        y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
      })(window, document, 'clarity', 'script', CLARITY_ID);
    }
  }

  // ---------- zmazanie analytických cookies pri odvolaní ----------
  function zmazAnalytickeCookies() {
    var nazvy = ['_ga', '_gid', '_gat', '_clck', '_clsk', 'CLID', 'ANONCHK', 'MR', 'MUID', 'SM', 'SRM_B'];
    document.cookie.split('; ').forEach(function (c) {
      var n = c.split('=')[0];
      if (n.indexOf('_ga_') === 0 || n.indexOf('_gat_') === 0) nazvy.push(n);
    });
    var casti = location.hostname.split('.');
    var domeny = [undefined];
    for (var i = 0; i < casti.length - 1; i++) domeny.push('.' + casti.slice(i).join('.'));
    nazvy.forEach(function (n) {
      domeny.forEach(function (d) {
        document.cookie = n + '=; Max-Age=0; Path=/' + (d ? '; Domain=' + d : '');
      });
    });
  }

  // ---------- vzhľad lišty (hodnoty z design_handoff_nacenime/README.md) ----------
  var CSS = [
    '.nm-ck{position:fixed;left:12px;bottom:12px;z-index:2147483000;width:min(640px,calc(100% - 24px));max-height:calc(100vh - 24px);overflow:auto;box-sizing:border-box;',
    'background:#fff;color:#11122f;border:1.5px solid #272361;border-radius:12px;padding:20px;font:400 18px/1.5 "Archivo",system-ui,sans-serif}',
    '.nm-ck *{box-sizing:border-box}',
    '.nm-ck:focus{outline:none}',
    '.nm-ck h2{margin:0 0 8px;font:800 24px/1.05 "Archivo",system-ui,sans-serif;letter-spacing:-0.02em;color:#272361}',
    '.nm-ck p{margin:0 0 16px;max-width:62ch}',
    '.nm-ck-tl{display:flex;gap:12px}',
    /* Hlavná lišta: kompaktná, aby nezakrývala formulár. Na mobile tlačidlá vedľa seba, na počítači vodorovný pruh. */
    '.nm-ck-hlavna{font-size:16px;padding:16px 20px}',
    '.nm-ck-hlavna h2{margin-bottom:4px}',
    '.nm-ck-hlavna p{margin:0 0 16px}',
    '.nm-ck-hlavna .nm-ck-odkaz{display:block;margin:12px auto 0;text-align:center}',
    '@media (min-width:860px){.nm-ck-hlavna{display:flex;align-items:center;gap:32px;width:min(1000px,calc(100% - 24px))}',
    '.nm-ck-hlavna .nm-ck-txt{flex:1 1 auto}.nm-ck-hlavna p{margin:0}',
    '.nm-ck-hlavna .nm-ck-akcie{flex:none;width:400px}}',
    '.nm-ck button{flex:1 1 0;min-width:0;min-height:60px;padding:0 12px;background:#fff;color:#272361;border:1.5px solid #272361;border-radius:12px;font:600 18px/1.2 "Archivo",system-ui,sans-serif;cursor:pointer}',
    '.nm-ck button:hover{background:#272361;color:#fff}',
    '.nm-ck button:focus-visible,.nm-ck a:focus-visible,.nm-ck input:focus-visible{outline:3px solid #272361;outline-offset:2px}',
    '.nm-ck .nm-ck-odkaz{display:inline-block;margin-top:16px;background:none;border:0;padding:4px 0;min-height:0;flex:none;color:#272361;text-decoration:underline;text-underline-offset:4px;font:600 18px/1.5 "Archivo",system-ui,sans-serif}',
    '.nm-ck .nm-ck-odkaz:hover{background:none;color:#11122f;text-decoration-thickness:3px}',
    '.nm-ck-riadok{display:flex;gap:16px;align-items:flex-start;min-height:60px;padding:12px 0;border-top:1.5px solid #dcd9d0}',
    '.nm-ck-riadok input{flex:none;width:28px;height:28px;margin:2px 0 0;accent-color:#272361}',
    '.nm-ck-riadok label{display:block;cursor:pointer}',
    '.nm-ck-riadok strong{display:block;font-weight:600;color:#272361}',
    '.nm-ck-riadok span{color:#11122f}',
    '.nm-ck-riadok.nm-ck-pevne label{cursor:default}',
    '.nm-ck-ulozit{margin-top:12px}',
    '@media (prefers-reduced-motion:reduce){.nm-ck,.nm-ck *{transition:none!important;animation:none!important}}'
  ].join('');

  var lista = null;
  var spustene = false; // či už bolo sledovanie spustené na tejto stránke

  function el(tag, attrs, deti) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') e.textContent = attrs[k]; else e.setAttribute(k, attrs[k]); });
    (deti || []).forEach(function (d) { e.appendChild(d); });
    return e;
  }
  function tlacidlo(text, naKlik, trieda) {
    var b = el('button', { type: 'button', text: text, 'class': trieda || '' });
    b.addEventListener('click', naKlik);
    return b;
  }

  function zatvor() {
    if (lista && lista.parentNode) lista.parentNode.removeChild(lista);
    lista = null;
  }

  function rozhodnutie(analytika) {
    var predtym = precitaj();
    uloz(analytika);
    zatvor();
    if (analytika) {
      spustene = true;
      nacitajSledovanie();
    } else if (spustene || (predtym && predtym.analytika)) {
      // Odvolanie: zmazať cookies a načítať stránku znova, aby načítané skripty prestali bežať.
      if (window.clarity) { try { window.clarity('consent', false); } catch (e) { /* ignoruj */ } }
      if (window.dataLayer) gtag('consent', 'update', { analytics_storage: 'denied' });
      zmazAnalytickeCookies();
      location.reload();
      return;
    }
    document.dispatchEvent(new CustomEvent('nm:cookies', { detail: { analytika: analytika } }));
  }

  function vytvorRamec() {
    zatvor();
    lista = el('div', { 'class': 'nm-ck', role: 'dialog', 'aria-modal': 'false', 'aria-labelledby': 'nm-ck-nadpis', 'aria-describedby': 'nm-ck-popis', tabindex: '-1' });
    return lista;
  }

  function ukazHlavnu() {
    var r = vytvorRamec();
    r.className += ' nm-ck-hlavna';
    r.appendChild(el('div', { 'class': 'nm-ck-txt' }, [
      el('h2', { id: 'nm-ck-nadpis', text: 'Cookies' }),
      el('p', { id: 'nm-ck-popis', text: 'Na meranie návštevnosti používame analytické cookies (Google Analytics, Microsoft Clarity). Spustia sa až po vašom súhlase.' })
    ]));
    r.appendChild(el('div', { 'class': 'nm-ck-akcie' }, [
      el('div', { 'class': 'nm-ck-tl' }, [
        tlacidlo('Prijať všetko', function () { rozhodnutie(true); }),
        tlacidlo('Odmietnuť', function () { rozhodnutie(false); })
      ]),
      tlacidlo('Nastavenia', function () { ukazNastavenia(precitaj()); }, 'nm-ck-odkaz')
    ]));
    document.body.appendChild(r);
    r.focus();
  }

  function ukazNastavenia(aktualne) {
    var r = vytvorRamec();
    r.appendChild(el('h2', { id: 'nm-ck-nadpis', text: 'Nastavenia cookies' }));
    r.appendChild(el('p', { id: 'nm-ck-popis', text: 'Vyberte, ktoré cookies môžeme používať. Rozhodnutie môžete kedykoľvek zmeniť cez odkaz Cookies v pätičke.' }));
    r.appendChild(el('p', {}, [
      el('a', { href: ODKAZ_COOKIES, text: 'Zásady cookies' }),
      document.createTextNode(' · '),
      el('a', { href: ODKAZ_OSOBNE_UDAJE, text: 'Ochrana osobných údajov' })
    ]));

    var nevyhnutne = el('div', { 'class': 'nm-ck-riadok nm-ck-pevne' }, [
      el('input', { type: 'checkbox', id: 'nm-ck-nev', checked: 'checked', disabled: 'disabled' }),
      el('label', { 'for': 'nm-ck-nev' }, [el('strong', { text: 'Nevyhnutné (vždy zapnuté)' }), el('span', { text: 'Uložia vaše rozhodnutie o cookies.' })])
    ]);
    var ana = el('input', { type: 'checkbox', id: 'nm-ck-ana' });
    if (aktualne && aktualne.analytika) ana.checked = true;
    var analytika = el('div', { 'class': 'nm-ck-riadok' }, [
      ana,
      el('label', { 'for': 'nm-ck-ana' }, [el('strong', { text: 'Analytické' }), el('span', { text: 'Merajú, koľko ľudí web navštívilo a ako sa po ňom pohybujú (Google Analytics cez Google Tag Manager, Microsoft Clarity).' })])
    ]);
    r.appendChild(nevyhnutne);
    r.appendChild(analytika);
    r.appendChild(el('div', { 'class': 'nm-ck-tl nm-ck-ulozit' }, [
      tlacidlo('Uložiť výber', function () { rozhodnutie(ana.checked); }),
      tlacidlo('Späť', function () { if (precitaj()) zatvor(); else ukazHlavnu(); })
    ]));
    r.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.stopPropagation(); if (precitaj()) zatvor(); else ukazHlavnu(); } // Esc nie je rozhodnutie
    });
    document.body.appendChild(r);
    ana.focus();
  }

  function init() {
    var style = document.createElement('style');
    style.textContent = CSS;
    document.head.appendChild(style);

    var ulozene = precitaj();
    if (ulozene && ulozene.analytika) { spustene = true; nacitajSledovanie(); }
    if (!ulozene) ukazHlavnu();

    // Odkaz „Cookies“ v pätičke (alebo akýkoľvek prvok s data-nm-cookies) znova otvorí nastavenia.
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a,button,[data-nm-cookies]');
      if (!a || a.closest('.nm-ck')) return;
      var jeCookies = a.hasAttribute('data-nm-cookies') || (a.closest('footer') && a.textContent.trim().toLowerCase() === 'cookies');
      if (jeCookies) { e.preventDefault(); ukazNastavenia(precitaj()); }
    }, true);

    window.NacenimeCookies = {
      otvor: function () { ukazNastavenia(precitaj()); },
      stav: function () { var s = precitaj(); return s ? { analytika: s.analytika } : null; }
    };
  }

  if (document.body) init(); else document.addEventListener('DOMContentLoaded', init);
})();
