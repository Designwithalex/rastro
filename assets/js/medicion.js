/* ============================================================
   medicion.js — Meta Pixel y Google Analytics.

   Pedido del cliente (22/10): medir visitas y compras para la
   pauta. Los IDs se cargan en el panel (Configuración → Medición)
   y llegan en data- del propio <script>, porque el CSP del sitio
   no deja scripts en línea: este archivo hace lo que harían los
   snippets oficiales.

   La compra se manda desde /checkout/retorno cuando el pago está
   aprobado (o el pedido por transferencia quedó anotado), una sola
   vez por pedido aunque recarguen la página.
   ============================================================ */

(function () {
  'use strict';

  var yo = document.currentScript;

  if (!yo) {
    return;
  }

  var pixel = yo.getAttribute('data-pixel') || '';
  var ga = yo.getAttribute('data-ga') || '';
  var compra = null;

  try {
    compra = JSON.parse(yo.getAttribute('data-compra') || 'null');
  } catch (e) {
    compra = null;
  }

  function cargar(src) {
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
  }

  /* --- Google Analytics 4 ----------------------------------- */
  if (ga) {
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', ga);
    cargar('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga));
  }

  /* --- Meta Pixel --------------------------------------------- */
  if (pixel) {
    var fbq = window.fbq = function () {
      if (fbq.callMethod) {
        fbq.callMethod.apply(fbq, arguments);
      } else {
        fbq.queue.push(arguments);
      }
    };
    if (!window._fbq) {
      window._fbq = fbq;
    }
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    fbq('init', pixel);
    fbq('track', 'PageView');
    cargar('https://connect.facebook.net/en_US/fbevents.js');
  }

  /* --- Compra -------------------------------------------------- */
  if (compra && compra.codigo) {
    var clave = 'rastro-compra-' + compra.codigo;
    var yaMandada = false;

    try {
      yaMandada = window.localStorage.getItem(clave) === '1';
    } catch (e) {
      yaMandada = false;
    }

    if (!yaMandada) {
      if (ga) {
        window.gtag('event', 'purchase', {
          transaction_id: compra.codigo,
          value: compra.valor,
          currency: 'ARS',
          items: compra.items || []
        });
      }
      if (pixel) {
        window.fbq('track', 'Purchase', { value: compra.valor, currency: 'ARS' }, { eventID: compra.codigo });
      }

      try {
        window.localStorage.setItem(clave, '1');
      } catch (e) {
        /* sin almacenamiento: a lo sumo se repite en una recarga */
      }
    }
  }
}());
