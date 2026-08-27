/* ============================================================
   carrito-pagina.js — Rastro Fitness
   Dibuja la página /carrito a partir de lo que hay en
   localStorage. Sólo corre en esa página.

   NO CALCULA DESCUENTOS. Los dos precios de cada producto
   —publicado y por transferencia— vienen resueltos desde PHP en
   el atributo data-catalogo, que los sacó de
   precio_con_descuento(). Acá se multiplica por la cantidad y se
   suma, y de eso se encarga Carrito.calcularTotales(). Si en
   este archivo aparece un porcentaje, hay dos fuentes de verdad
   para el dato más importante del sitio.

   Depende de carrito.js (window.Carrito) y por eso footer.php lo
   carga después: los defer corren en el orden del marcado.
   ============================================================ */

(function (global) {
  'use strict';

  var raiz = document.querySelector('[data-carrito-pagina]');

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

  var vacio = raiz.querySelector('[data-carrito-vacio]');
  var cuerpo = raiz.querySelector('[data-carrito-cuerpo]');
  var lista = raiz.querySelector('[data-carrito-lineas]');

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

  /* --- Una fila ---------------------------------------------- */

  function crear(etiqueta, clase, contenido) {
    var nodo = document.createElement(etiqueta);

    if (clase) { nodo.className = clase; }
    if (contenido !== undefined) { nodo.textContent = contenido; }

    return nodo;
  }

  function dibujarLinea(item, producto) {
    var li = crear('li', 'linea');

    // Foto
    var foto = crear('a', 'linea__foto');
    foto.href = producto.url;
    foto.setAttribute('tabindex', '-1');
    foto.setAttribute('aria-hidden', 'true');

    if (producto.imagen) {
      var img = new Image();
      img.src = producto.imagen;
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      foto.appendChild(img);
    }

    li.appendChild(foto);

    // Identidad
    var datos = crear('div', 'linea__datos');
    datos.appendChild(crear('p', 'linea__sku t-mono-label-sm', producto.sku));

    var nombre = crear('h2', 'linea__nombre t-display-s');
    var enlace = crear('a', 'linea__enlace', producto.nombre);
    enlace.href = producto.url;
    nombre.appendChild(enlace);
    datos.appendChild(nombre);

    datos.appendChild(crear('p', 'linea__unitario t-mono-texto', moneda(producto.publicado) + ' por unidad'));
    li.appendChild(datos);

    // Cantidad
    var stepper = crear('div', 'stepper linea__stepper');

    var menos = crear('button', 'stepper__paso', '−');
    menos.type = 'button';
    menos.setAttribute('aria-label', 'Quitar una unidad de ' + producto.nombre);
    menos.addEventListener('click', function () {
      global.Carrito.fijar(item.id, item.cantidad - 1);
    });

    var campo = document.createElement('input');
    campo.className = 'stepper__campo t-mono-label';
    campo.type = 'number';
    campo.min = '1';
    campo.step = '1';
    campo.value = String(item.cantidad);
    campo.setAttribute('aria-label', 'Cantidad de ' + producto.nombre);

    if (producto.stock > 0) {
      campo.max = String(producto.stock);
    }

    campo.addEventListener('change', function () {
      global.Carrito.fijar(item.id, parseInt(campo.value, 10) || 0);
    });

    var mas = crear('button', 'stepper__paso', '+');
    mas.type = 'button';
    mas.setAttribute('aria-label', 'Agregar una unidad de ' + producto.nombre);
    mas.addEventListener('click', function () {
      global.Carrito.fijar(item.id, item.cantidad + 1);
    });

    stepper.appendChild(menos);
    stepper.appendChild(campo);
    stepper.appendChild(mas);
    li.appendChild(stepper);

    // Importe de la línea
    var importe = crear('div', 'linea__importe');

    var publicado = crear('p', 'linea__publicado');
    publicado.appendChild(crear('span', 'plata t-precio-md', moneda(producto.publicado * item.cantidad)));
    importe.appendChild(publicado);

    if (producto.transferencia < producto.publicado) {
      importe.appendChild(crear('p', 'linea__transferencia t-mono-dato', moneda(producto.transferencia * item.cantidad)));
      importe.appendChild(crear('p', 'linea__rotulo t-mono-texto-sm', 'transferencia'));
    }

    li.appendChild(importe);

    // Quitar
    var quitar = crear('button', 'linea__quitar t-mono-label-sm', 'Eliminar');
    quitar.type = 'button';
    quitar.setAttribute('aria-label', 'Quitar ' + producto.nombre + ' del carrito');
    quitar.addEventListener('click', function () {
      global.Carrito.quitar(item.id);
    });

    li.appendChild(quitar);

    return li;
  }

  /* --- El aviso de un producto que ya no existe --------------
     Puede pasar: el carrito sobrevive en el navegador y mientras
     tanto el panel dio de baja el producto. Se avisa y se ofrece
     sacarlo, en vez de hacerlo desaparecer sin explicación. */

  function dibujarHuerfano(item) {
    var li = crear('li', 'linea linea--huerfana');

    li.appendChild(crear('p', 'linea__aviso t-mono-texto',
      'Uno de los productos que tenías cargado ya no está disponible.'));

    var quitar = crear('button', 'linea__quitar t-mono-label-sm', 'Quitar');
    quitar.type = 'button';
    quitar.addEventListener('click', function () {
      global.Carrito.quitar(item.id);
    });

    li.appendChild(quitar);

    return li;
  }

  /* --- Pintar ------------------------------------------------ */

  function pintar() {
    var items = global.Carrito.items();

    vacio.hidden = items.length > 0;
    cuerpo.hidden = items.length === 0;

    lista.textContent = '';

    var lineas = [];

    items.forEach(function (item) {
      var producto = CATALOGO[String(item.id)];

      if (!producto) {
        lista.appendChild(dibujarHuerfano(item));
        return;
      }

      lista.appendChild(dibujarLinea(item, producto));

      lineas.push({
        cantidad: item.cantidad,
        precioPublicado: producto.publicado,
        precioTransferencia: producto.transferencia
      });
    });

    var totales = global.Carrito.calcularTotales(lineas, CONFIG);

    /* Dos números distintos y los dos importan: cuántos productos
       distintos y cuántas unidades en total. "4 productos" con tres
       productos cargados y uno repetido es una cuenta que no cierra
       con lo que se ve en pantalla. */
    var conteo = lineas.length + (lineas.length === 1 ? ' producto' : ' productos');

    if (totales.unidades !== lineas.length) {
      conteo += ' · ' + totales.unidades + ' unidades';
    }

    texto(raiz.querySelector('[data-carrito-resumen-conteo]'), conteo);
    texto(raiz.querySelector('[data-carrito-resumen-productos]'), conteo);
    texto(raiz.querySelector('[data-carrito-resumen-subtotal]'), moneda(totales.subtotal));

    // Sin tabla de costos de envío no se inventa un número: se dice que
    // se coordina (PENDIENTES #8).
    texto(raiz.querySelector('[data-carrito-resumen-envio]'),
      totales.envioGratis ? 'Gratis' : 'A coordinar');

    texto(raiz.querySelector('[data-carrito-resumen-ahorro]'), moneda(-totales.ahorro));
    texto(raiz.querySelector('[data-carrito-total]'), moneda(totales.total));
    texto(raiz.querySelector('[data-carrito-total-transferencia]'), moneda(totales.totalTransferencia));
    texto(raiz.querySelector('[data-carrito-nota-ahorro]'),
      'transferencia o efectivo · ahorrás ' + moneda(totales.ahorro));
  }

  global.addEventListener(global.Carrito.EVENTO, pintar);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pintar);
  } else {
    pintar();
  }
}(window));
