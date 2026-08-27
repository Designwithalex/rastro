/* ============================================================
   checkout.js — Rastro Fitness
   Dibuja el resumen de /checkout y llena el campo oculto que
   viaja en el POST.

   QUÉ HACE Y QUÉ NO

   Hace dos cosas: pinta el resumen del pedido y mantiene
   sincronizado el campo `items` con lo que hay en localStorage.

   NO decide precios. Los dos precios de cada producto vienen
   resueltos desde PHP en data-catalogo, igual que en /carrito.
   Y sobre todo: lo que se manda en el POST son SOLO `id` y
   `cantidad`. El servidor vuelve a resolver los precios contra
   el repository, así que lo que se dibuje acá es información
   para quien mira, nunca la base del cobro.

   Depende de carrito.js (window.Carrito) y por eso footer.php
   lo carga después: los defer corren en el orden del marcado.
   ============================================================ */

(function (global) {
  'use strict';

  var raiz = document.querySelector('[data-checkout]');

  if (!raiz || !global.Carrito) {
    return;
  }

  function leerDatos(nombre) {
    try {
      return JSON.parse(raiz.getAttribute(nombre) || '{}');
    } catch (e) {
      // Un atributo roto no puede tumbar la página: se dibuja el vacío.
      return {};
    }
  }

  var CATALOGO = leerDatos('data-catalogo');
  var CONFIG = leerDatos('data-config');

  var vacio = raiz.querySelector('[data-checkout-vacio]');
  var cuerpo = raiz.querySelector('[data-checkout-cuerpo]');
  var lista = raiz.querySelector('[data-checkout-lineas]');
  var campoItems = raiz.querySelector('[data-checkout-items]');
  var boton = raiz.querySelector('[data-checkout-enviar]');
  var botonTexto = raiz.querySelector('[data-checkout-enviar-texto]');

  /* --- Formato ----------------------------------------------
     El mismo que moneda() de helpers.php: $189.400, punto de
     miles y sin decimales, porque los precios son enteros. */

  function moneda(n) {
    var negativo = n < 0;
    var entero = Math.round(Math.abs(n));
    var texto = String(entero).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return (negativo ? '-' : '') + '$' + texto;
  }

  function texto(nodo, valor) {
    if (nodo) {
      nodo.textContent = valor;
    }
  }

  function ver(nodo, mostrar) {
    if (nodo) {
      nodo.hidden = !mostrar;
    }
  }

  /* --- Estado ------------------------------------------------ */

  /** El medio de pago elegido. Decide qué precio se muestra. */
  function medioElegido() {
    var marcado = raiz.querySelector('[data-checkout-medio]:checked');

    return marcado ? marcado.value : 'mercado_pago';
  }

  /**
   * Cruza lo que hay en localStorage con el índice del catálogo.
   * Un id que no está en el índice se descarta acá y el servidor
   * lo vuelve a descartar: es un producto dado de baja.
   */
  function lineas() {
    return global.Carrito.items()
      .map(function (item) {
        var ficha = CATALOGO[String(item.id)];

        if (!ficha) {
          return null;
        }

        return {
          id: item.id,
          cantidad: item.cantidad,
          nombre: ficha.nombre,
          sku: ficha.sku,
          imagen: ficha.imagen,
          url: ficha.url,
          precioPublicado: ficha.publicado,
          precioTransferencia: ficha.transferencia
        };
      })
      .filter(Boolean);
  }

  /* --- Pintado ----------------------------------------------- */

  function pintarLineas(datos, medio) {
    if (!lista) {
      return;
    }

    lista.textContent = '';

    datos.forEach(function (linea) {
      var unitario = medio === 'transferencia'
        ? linea.precioTransferencia
        : linea.precioPublicado;

      var li = document.createElement('li');
      li.className = 'resumen__item';

      var nombre = document.createElement('span');
      nombre.className = 'resumen__item-nombre t-body-sm';
      nombre.textContent = linea.nombre;

      var detalle = document.createElement('span');
      detalle.className = 'resumen__item-detalle t-mono-texto-sm';
      detalle.textContent = linea.cantidad + ' × ' + moneda(unitario);

      li.appendChild(nombre);
      li.appendChild(detalle);
      lista.appendChild(li);
    });
  }

  function pintar() {
    var datos = lineas();
    var medio = medioElegido();

    // El campo oculto se llena SIEMPRE, aunque el resumen esté vacío:
    // así el servidor recibe una lista vacía y contesta con un mensaje
    // claro, en vez de recibir un campo sin valor y no saber qué pasó.
    if (campoItems) {
      campoItems.value = JSON.stringify(datos.map(function (linea) {
        return { id: linea.id, cantidad: linea.cantidad };
      }));
    }

    ver(vacio, datos.length === 0);
    ver(cuerpo, datos.length > 0);

    if (datos.length === 0) {
      return;
    }

    var totales = global.Carrito.calcularTotales(datos, {
      envioGratisDesde: CONFIG.envioGratisDesde,
      costoEnvio: 0
    });

    var transferencia = medio === 'transferencia';
    var total = transferencia ? totales.totalTransferencia : totales.total;

    pintarLineas(datos, medio);

    texto(raiz.querySelector('[data-checkout-conteo]'),
      '[' + ('0' + totales.unidades).slice(-2) + '] ' +
      (totales.unidades === 1 ? 'producto' : 'productos'));

    texto(raiz.querySelector('[data-checkout-resumen-productos]'),
      'Productos (' + totales.unidades + ')');

    texto(raiz.querySelector('[data-checkout-resumen-subtotal]'),
      moneda(transferencia ? totales.subtotalTransferencia : totales.subtotal));

    // El envío es "a coordinar" mientras no exista la tabla real
    // (PENDIENTES #8). No se inventa un número.
    texto(raiz.querySelector('[data-checkout-resumen-envio]'),
      totales.envioGratis ? 'Bonificado' : 'A coordinar');

    ver(raiz.querySelector('[data-checkout-fila-ahorro]'), transferencia);
    texto(raiz.querySelector('[data-checkout-resumen-ahorro]'), '-' + moneda(totales.ahorro));

    texto(raiz.querySelector('[data-checkout-total]'), moneda(total));

    texto(raiz.querySelector('[data-checkout-nota]'), transferencia
      ? 'Coordinamos por WhatsApp cómo hacer la transferencia.'
      : 'Te llevamos al sitio de Mercado Pago para completar el pago.');

    texto(botonTexto, transferencia ? 'Confirmar pedido' : 'Ir a pagar');
  }

  /* --- Eventos ------------------------------------------------ */

  raiz.addEventListener('change', function (evento) {
    if (evento.target.hasAttribute('data-checkout-medio')) {
      pintar();
    }
  });

  // Si el carrito cambia en otra pestaña mientras alguien completa el
  // formulario, el resumen se actualiza en vez de quedar mintiendo.
  global.addEventListener(global.Carrito.EVENTO, pintar);
  global.addEventListener('storage', function (evento) {
    if (evento.key === global.Carrito.CLAVE) {
      pintar();
    }
  });

  /* Doble clic en "Ir a pagar" = dos pedidos y dos preferencias. La
     clave de idempotencia del lado de PHP cubre el reintento sobre el
     MISMO pedido, pero no un segundo POST que crea otro. Se bloquea
     el botón al enviar.

     El formulario no se deshabilita entero: un <input disabled> no
     viaja en el POST y se perderían todos los datos. */
  var formulario = raiz.querySelector('form');

  if (formulario && boton) {
    formulario.addEventListener('submit', function () {
      boton.disabled = true;
      texto(botonTexto, 'Un momento…');

      // Si el POST vuelve con errores de validación la página se
      // vuelve a dibujar entera y el botón vuelve solo. Este respaldo
      // es para el caso raro de que el navegador restaure la página
      // desde su caché al volver atrás.
      global.setTimeout(function () {
        boton.disabled = false;
        pintar();
      }, 8000);
    });
  }

  pintar();
}(window));
