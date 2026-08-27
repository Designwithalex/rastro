/* ============================================================
   carrito.js — Rastro Fitness
   El carrito vive en localStorage. Este archivo guarda apenas lo
   mínimo (id y cantidad) y mantiene el contador de la cabecera.

   IMPORTANTE — el descuento no se calcula acá.
   El porcentaje por transferencia lo resuelve precio_con_descuento()
   en app/helpers.php, del lado del servidor. La vista del carrito
   imprime en cada línea el precio publicado y el precio con descuento
   ya calculados, y este archivo solo multiplica por la cantidad y suma.
   Si un día aparece un cálculo de porcentaje en este archivo, algo se
   rompió: hay una sola fuente de verdad y está en PHP.
   ============================================================ */

(function (global) {
  'use strict';

  var CLAVE = 'rastro:carrito';
  var EVENTO = 'rastro:carrito-cambio';

  /* --- Almacenamiento -------------------------------------- */

  function disponible() {
    try {
      var prueba = '__rastro__';
      global.localStorage.setItem(prueba, prueba);
      global.localStorage.removeItem(prueba);
      return true;
    } catch (e) {
      // Navegación privada o cookies bloqueadas: el sitio sigue andando,
      // solo que el carrito no sobrevive a la recarga.
      return false;
    }
  }

  var hayAlmacenamiento = disponible();
  var memoria = [];

  function normalizar(bruto) {
    if (!Array.isArray(bruto)) {
      return [];
    }

    return bruto
      .map(function (item) {
        return {
          id: parseInt(item && item.id, 10),
          cantidad: parseInt(item && item.cantidad, 10)
        };
      })
      .filter(function (item) {
        return Number.isInteger(item.id) && item.id > 0 &&
               Number.isInteger(item.cantidad) && item.cantidad > 0;
      });
  }

  function leer() {
    if (!hayAlmacenamiento) {
      return memoria.slice();
    }

    try {
      return normalizar(JSON.parse(global.localStorage.getItem(CLAVE) || '[]'));
    } catch (e) {
      return [];
    }
  }

  function guardar(items) {
    memoria = normalizar(items);

    if (hayAlmacenamiento) {
      try {
        global.localStorage.setItem(CLAVE, JSON.stringify(memoria));
      } catch (e) {
        hayAlmacenamiento = false;
      }
    }

    global.dispatchEvent(new CustomEvent(EVENTO, { detail: { items: memoria.slice() } }));

    return memoria.slice();
  }

  /* --- Operaciones ------------------------------------------ */

  function agregar(id, cantidad) {
    var items = leer();
    var suma = parseInt(cantidad, 10) || 1;
    var encontrado = false;

    items = items.map(function (item) {
      if (item.id === parseInt(id, 10)) {
        encontrado = true;
        return { id: item.id, cantidad: item.cantidad + suma };
      }
      return item;
    });

    if (!encontrado) {
      items.push({ id: parseInt(id, 10), cantidad: suma });
    }

    return guardar(items);
  }

  function fijar(id, cantidad) {
    var objetivo = parseInt(id, 10);
    var nueva = parseInt(cantidad, 10) || 0;

    if (nueva <= 0) {
      return quitar(objetivo);
    }

    var items = leer().map(function (item) {
      return item.id === objetivo ? { id: item.id, cantidad: nueva } : item;
    });

    return guardar(items);
  }

  function quitar(id) {
    var objetivo = parseInt(id, 10);

    return guardar(leer().filter(function (item) {
      return item.id !== objetivo;
    }));
  }

  function vaciar() {
    return guardar([]);
  }

  function unidades(items) {
    return (items || leer()).reduce(function (suma, item) {
      return suma + item.cantidad;
    }, 0);
  }

  /* --- Totales ----------------------------------------------
     Función pura: mismas entradas, misma salida, sin tocar el DOM
     ni el almacenamiento. Los dos precios de cada línea vienen ya
     calculados desde PHP.

     lineas:   [{ cantidad, precioPublicado, precioTransferencia }]
     opciones: { envioGratisDesde, costoEnvio }
     ---------------------------------------------------------- */

  function calcularTotales(lineas, opciones) {
    var config = opciones || {};
    var envioGratisDesde = Number(config.envioGratisDesde) || 0;
    var costoEnvio = Number(config.costoEnvio) || 0;

    var resumen = (lineas || []).reduce(function (acumulado, linea) {
      var cantidad = Number(linea.cantidad) || 0;
      var publicado = Number(linea.precioPublicado) || 0;
      var transferencia = Number(linea.precioTransferencia);

      if (!Number.isFinite(transferencia)) {
        transferencia = publicado;
      }

      acumulado.unidades += cantidad;
      acumulado.subtotal += publicado * cantidad;
      acumulado.subtotalTransferencia += transferencia * cantidad;

      return acumulado;
    }, { unidades: 0, subtotal: 0, subtotalTransferencia: 0 });

    // TODO(backend): el umbral se compara contra el subtotal PUBLICADO, no
    // contra el de transferencia. Un carrito de $170.000 publicado que paga
    // $145.000 por transferencia hoy tiene envío gratis. Nadie definió si
    // corresponde: está anotado en PENDIENTES #48 y lo decide el cliente.
    // Si la respuesta es "contra lo que efectivamente paga", el único cambio
    // es usar resumen.subtotalTransferencia en esta línea y el equivalente
    // del lado de PHP cuando exista el checkout.
    var envioGratis = envioGratisDesde > 0 && resumen.subtotal >= envioGratisDesde;
    var envio = envioGratis ? 0 : costoEnvio;

    return {
      unidades: resumen.unidades,
      subtotal: resumen.subtotal,
      subtotalTransferencia: resumen.subtotalTransferencia,
      ahorro: resumen.subtotal - resumen.subtotalTransferencia,
      envio: envio,
      envioGratis: envioGratis,
      falta_para_envio_gratis: envioGratis ? 0 : Math.max(0, envioGratisDesde - resumen.subtotal),
      total: resumen.subtotal + envio,
      totalTransferencia: resumen.subtotalTransferencia + envio
    };
  }

  /* --- Contador de la cabecera ------------------------------ */

  function pintarContador() {
    var cantidad = unidades();
    var etiqueta = '[' + (cantidad < 100 ? ('0' + cantidad).slice(-2) : String(cantidad)) + ']';

    Array.prototype.forEach.call(
      document.querySelectorAll('[data-carrito-contador]'),
      function (nodo) {
        nodo.textContent = etiqueta;
      }
    );
  }

  global.addEventListener(EVENTO, pintarContador);

  // Si el carrito cambia en otra pestaña, el contador de esta se entera.
  global.addEventListener('storage', function (evento) {
    if (evento.key === CLAVE) {
      pintarContador();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pintarContador);
  } else {
    pintarContador();
  }

  /* --- Botones "Agregar al carrito" -------------------------
     Delegado en el documento y no un listener por botón: las cards
     las dibuja PHP, pero el catálogo va a poder filtrar sin recargar
     y ahí los botones aparecen después de este archivo. Con
     delegación, los que aparezcan más tarde ya funcionan.

     El botón confirma en su propia etiqueta, no con un cartel: el
     contador de la cabecera ya cambió y un aviso flotante taparía
     la grilla justo cuando la persona sigue mirando productos. */

  var TEXTO_OK = 'Agregado';
  var ESPERA_OK = 1600;

  function confirmar(boton) {
    var etiqueta = boton.querySelector('.t-mono-label') || boton;

    if (boton.dataset.textoOriginal === undefined) {
      boton.dataset.textoOriginal = etiqueta.textContent;
    }

    etiqueta.textContent = TEXTO_OK;
    boton.classList.add('es-agregado');

    global.clearTimeout(boton._rastroTimer);
    boton._rastroTimer = global.setTimeout(function () {
      etiqueta.textContent = boton.dataset.textoOriginal;
      boton.classList.remove('es-agregado');
    }, ESPERA_OK);
  }

  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest ? evento.target.closest('[data-agregar]') : null;

    if (!boton) {
      return;
    }

    var id = parseInt(boton.getAttribute('data-agregar'), 10);

    if (!Number.isInteger(id) || id <= 0) {
      return;
    }

    // La card entera es un enlace a la ficha y el botón está encima:
    // sin esto, agregar al carrito además navega.
    evento.preventDefault();

    var cantidad = parseInt(boton.getAttribute('data-cantidad'), 10);

    agregar(id, Number.isInteger(cantidad) && cantidad > 0 ? cantidad : 1);
    confirmar(boton);
  });

  /* --- API pública ------------------------------------------ */

  global.Carrito = {
    CLAVE: CLAVE,
    EVENTO: EVENTO,
    items: leer,
    agregar: agregar,
    fijar: fijar,
    quitar: quitar,
    vaciar: vaciar,
    unidades: unidades,
    calcularTotales: calcularTotales,
    pintarContador: pintarContador
  };
}(window));
