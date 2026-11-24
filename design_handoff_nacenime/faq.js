// Časté otázky: otvorenie odpovede podľa kotvy (#kontakt), vyhľadávanie a zvýraznenie skupiny v bočnom zozname.
(function () {
  'use strict';
  var otazky = Array.prototype.slice.call(document.querySelectorAll('.faq-skupina details'));
  var skupiny = Array.prototype.slice.call(document.querySelectorAll('.faq-skupina'));

  function otvorPodlaKotvy() {
    var id = decodeURIComponent(location.hash.slice(1));
    if (!id) return;
    var d = document.getElementById(id);
    if (d && d.tagName === 'DETAILS') {
      d.open = true;
      d.scrollIntoView({ block: 'start' });
      var s = d.querySelector('summary');
      if (s) s.focus({ preventScroll: true });
    }
  }
  otvorPodlaKotvy();
  window.addEventListener('hashchange', otvorPodlaKotvy);

  // Vyhľadávanie bez ohľadu na diakritiku a veľkosť písmen.
  var pole = document.getElementById('hladaj');
  var stav = document.getElementById('hladaj-stav');
  var bezDiakritiky = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
  var texty = otazky.map(function (d) { return bezDiakritiky(d.textContent); });
  if (pole) {
    pole.addEventListener('input', function () {
      var q = bezDiakritiky(pole.value.trim());
      var pocet = 0;
      otazky.forEach(function (d, i) {
        var zhoda = !q || texty[i].indexOf(q) !== -1;
        d.hidden = !zhoda;
        if (zhoda) pocet++;
        if (q && zhoda) d.open = true;
      });
      skupiny.forEach(function (s) { s.hidden = !s.querySelector('details:not([hidden])'); });
      stav.textContent = q ? (pocet === 0 ? 'Nenašli sme žiadnu otázku. Napíšte nám na info@nacenime.sk.' : 'Nájdené otázky: ' + pocet + '.') : '';
    });
  }

  // Bočný zoznam: zvýrazní skupinu, ktorá je práve na obrazovke.
  var odkazy = Array.prototype.slice.call(document.querySelectorAll('.toc a'));
  if ('IntersectionObserver' in window && odkazy.length) {
    var io = new IntersectionObserver(function (zaznamy) {
      zaznamy.forEach(function (z) {
        if (!z.isIntersecting) return;
        odkazy.forEach(function (a) {
          var aktivny = a.getAttribute('href') === '#' + z.target.id;
          a.classList.toggle('on', aktivny);
          if (aktivny) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    skupiny.forEach(function (s) { io.observe(s); });
  }
})();
