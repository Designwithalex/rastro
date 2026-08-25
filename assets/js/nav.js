/* ============================================================
   nav.js — Rastro Fitness
   Tres piezas de navegación, sin dependencias:

     1. el mega-menú de PRODUCTOS en escritorio,
     2. el cajón de celular,
     3. el buscador plegable de la barra de celular.

   Las tres comparten una idea: el marcado ya está completo y en el
   orden correcto cuando llega el JavaScript. Este archivo no escribe
   HTML, solo prende y apaga estados. Si no corre, la página sigue
   teniendo un menú (ver assets/css/sin-js.css).

   LA DIFERENCIA QUE IMPORTA
   El cajón de celular es un diálogo: tapa la página, así que atrapa el
   Tab, bloquea el scroll de atrás y devuelve el foco al salir. El panel
   de escritorio NO lo es: deja ver la página, no es modal, y atrapar el
   Tab adentro sería encerrar al usuario en un menú. Ahí Tab avanza
   linealmente y salir del último elemento cierra el panel.
   ============================================================ */

(function (global) {
  'use strict';

  var doc = global.document;

  /* Retardos de intención. El de apertura evita que el panel salte al
     cruzar el menú camino al buscador; el de cierre da tiempo a bajar en
     diagonal hasta el panel sin que se escape. */
  var RETARDO_ABRIR = 120;
  var RETARDO_CERRAR = 240;
  var DURACION_CAJON = 200;

  var SELECTOR_FOCO = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  function consulta(medida) {
    return global.matchMedia ? global.matchMedia(medida) : null;
  }

  function menosMovimiento() {
    var mq = consulta('(prefers-reduced-motion: reduce)');

    return !!(mq && mq.matches);
  }

  /* Solo lo que se puede ver se puede enfocar: un elemento adentro de algo
     con display:none no devuelve rectángulos. */
  function enfocables(raiz) {
    return Array.prototype.filter.call(
      raiz.querySelectorAll(SELECTOR_FOCO),
      function (nodo) {
        return nodo.getClientRects().length > 0;
      }
    );
  }

  function escucharMedida(mq, alCambiar) {
    if (!mq) {
      return;
    }

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', alCambiar);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(alCambiar);
    }
  }

  /* ==========================================================
     1. Mega-menú de escritorio
     ========================================================== */

  function iniciarMega() {
    var contenedor = doc.querySelector('[data-mega]');

    if (!contenedor) {
      return;
    }

    var disparador = contenedor.querySelector('[data-mega-disparador]');
    var panel = contenedor.querySelector('[data-mega-panel]');
    var velo = doc.querySelector('[data-mega-velo]');

    if (!disparador || !panel) {
      return;
    }

    var categorias = Array.prototype.slice.call(panel.querySelectorAll('[data-mega-categoria]'));
    var grupos = Array.prototype.slice.call(panel.querySelectorAll('[data-mega-grupo]'));
    var reloj = null;
    var abierto = false;

    function cancelar() {
      if (reloj) {
        global.clearTimeout(reloj);
        reloj = null;
      }
    }

    function abrir() {
      cancelar();

      if (abierto) {
        return;
      }

      panel.hidden = false;

      if (velo) {
        velo.hidden = false;
      }

      disparador.setAttribute('aria-expanded', 'true');
      contenedor.setAttribute('data-abierto', 'true');
      abierto = true;
    }

    /* devolverFoco solo cuando el usuario cerró a propósito (Esc, clic en
       el disparador). Si se fue con Tab, el foco ya está donde tiene que
       estar y traerlo de vuelta sería sacárselo de las manos. */
    function cerrar(devolverFoco) {
      cancelar();

      if (!abierto) {
        return;
      }

      panel.hidden = true;

      if (velo) {
        velo.hidden = true;
      }

      disparador.setAttribute('aria-expanded', 'false');
      contenedor.removeAttribute('data-abierto');
      abierto = false;

      if (devolverFoco) {
        disparador.focus();
      }
    }

    function slugActivo() {
      for (var i = 0; i < categorias.length; i++) {
        if (categorias[i].getAttribute('data-activa') === 'true') {
          return categorias[i].getAttribute('data-mega-categoria');
        }
      }

      return categorias.length ? categorias[0].getAttribute('data-mega-categoria') : null;
    }

    function indiceActivo() {
      for (var i = 0; i < categorias.length; i++) {
        if (categorias[i].getAttribute('data-activa') === 'true') {
          return i;
        }
      }

      return 0;
    }

    function grupoActivo() {
      var slug = slugActivo();

      for (var i = 0; i < grupos.length; i++) {
        if (grupos[i].getAttribute('data-mega-grupo') === slug) {
          return grupos[i];
        }
      }

      return null;
    }

    function activar(slug) {
      categorias.forEach(function (enlace) {
        if (enlace.getAttribute('data-mega-categoria') === slug) {
          enlace.setAttribute('data-activa', 'true');
        } else {
          enlace.removeAttribute('data-activa');
        }
      });

      grupos.forEach(function (grupo) {
        grupo.hidden = grupo.getAttribute('data-mega-grupo') !== slug;
      });
    }

    function enfocarCategoria(indice) {
      if (!categorias.length) {
        return;
      }

      var total = categorias.length;
      var destino = categorias[((indice % total) + total) % total];

      activar(destino.getAttribute('data-mega-categoria'));
      destino.focus();
    }

    /* --- Puntero --------------------------------------------
       El hover se engancha solo en dispositivos con puntero fino. En una
       pantalla táctil el navegador emula un mouseenter antes del clic:
       el panel se abriría por el falso hover y el clic lo cerraría al
       toque siguiente. */
    var punteroFino = consulta('(hover: hover) and (pointer: fine)');

    if (punteroFino && punteroFino.matches) {
      contenedor.addEventListener('mouseenter', function () {
        cancelar();
        reloj = global.setTimeout(abrir, RETARDO_ABRIR);
      });

      contenedor.addEventListener('mouseleave', function () {
        cancelar();
        reloj = global.setTimeout(function () {
          if (!contenedor.contains(doc.activeElement)) {
            cerrar(false);
          }
        }, RETARDO_CERRAR);
      });

      categorias.forEach(function (enlace) {
        enlace.addEventListener('mouseenter', function () {
          activar(enlace.getAttribute('data-mega-categoria'));
        });
      });
    }

    /* --- Disparador ------------------------------------------ */

    disparador.addEventListener('click', function (evento) {
      if (abierto) {
        cerrar(true);
        return;
      }

      abrir();

      // detail 0 = el clic lo produjo el teclado (Enter o Espacio sobre el
      // botón). Ahí sí corresponde meter el foco adentro del panel; con el
      // mouse sería robarle el cursor a alguien que solo pasó por encima.
      if (evento.detail === 0) {
        enfocarCategoria(0);
      }
    });

    /* --- Teclado ---------------------------------------------
       Todo el teclado se atiende en un solo lugar, el contenedor. Con un
       listener aparte en el disparador, la flecha abajo movía el foco a la
       primera categoría y el mismo evento seguía burbujeando hasta acá,
       donde ya se leía la categoría como foco actual y avanzaba a la
       segunda: dos saltos por una tecla.

       Esc cierra siempre y devuelve el foco. Las flechas recorren la
       columna A y cruzan a la B. Tab no se toca: avanza linealmente y,
       cuando se va del panel, el focusout de más abajo lo cierra. */
    contenedor.addEventListener('keydown', function (evento) {
      var tecla = evento.key;

      if (tecla === 'Escape' || tecla === 'Esc') {
        if (abierto) {
          evento.preventDefault();
          cerrar(true);
        }

        return;
      }

      if (doc.activeElement === disparador) {
        if (tecla === 'ArrowDown' || tecla === 'Down') {
          evento.preventDefault();
          abrir();
          enfocarCategoria(0);
        }

        return;
      }

      var indice = categorias.indexOf(doc.activeElement);

      if (indice !== -1) {
        if (tecla === 'ArrowDown' || tecla === 'Down') {
          evento.preventDefault();
          enfocarCategoria(indice + 1);
        } else if (tecla === 'ArrowUp' || tecla === 'Up') {
          evento.preventDefault();
          enfocarCategoria(indice - 1);
        } else if (tecla === 'Home') {
          evento.preventDefault();
          enfocarCategoria(0);
        } else if (tecla === 'End') {
          evento.preventDefault();
          enfocarCategoria(categorias.length - 1);
        } else if (tecla === 'ArrowRight' || tecla === 'Right') {
          var grupo = grupoActivo();
          var primero = grupo ? enfocables(grupo)[0] : null;

          if (primero) {
            evento.preventDefault();
            primero.focus();
          }
        }

        return;
      }

      if (tecla === 'ArrowLeft' || tecla === 'Left') {
        var actual = grupoActivo();

        if (actual && actual.contains(doc.activeElement)) {
          evento.preventDefault();
          enfocarCategoria(indiceActivo());
        }
      }
    });

    // El foco entra a una categoría: la columna B la acompaña.
    categorias.forEach(function (enlace) {
      enlace.addEventListener('focus', function () {
        activar(enlace.getAttribute('data-mega-categoria'));
      });
    });

    /* Tab que sale del panel lo cierra. Se mira después de que el foco se
       acomodó, y no se cierra si el mouse sigue adentro: hacer clic sobre
       un pedazo del panel que no es enfocable dispara focusout con destino
       nulo, y ahí cerrar sería un desastre. */
    contenedor.addEventListener('focusout', function () {
      if (!abierto) {
        return;
      }

      global.setTimeout(function () {
        if (!abierto || contenedor.contains(doc.activeElement)) {
          return;
        }

        if (contenedor.matches(':hover')) {
          return;
        }

        cerrar(false);
      }, 0);
    });

    // Clic en cualquier lado de afuera —el velo incluido— cierra.
    doc.addEventListener('click', function (evento) {
      if (abierto && !contenedor.contains(evento.target)) {
        cerrar(false);
      }
    });

    // Si la ventana se achica hasta el ancho del cajón, el panel deja de
    // existir en pantalla: hay que apagar el estado, no dejarlo colgado.
    var escritorio = consulta('(min-width: 1280px)');

    escucharMedida(escritorio, function (evento) {
      if (!evento.matches) {
        cerrar(false);
      }
    });
  }

  /* ==========================================================
     2. Cajón de celular — esto sí es un diálogo
     ========================================================== */

  function iniciarCajon() {
    var cajon = doc.querySelector('[data-cajon]');
    var abridor = doc.querySelector('[data-cajon-abrir]');

    if (!cajon || !abridor) {
      return;
    }

    var panel = cajon.querySelector('.cajon__panel');
    var reloj = null;
    var abierto = false;

    function abrir() {
      if (abierto) {
        return;
      }

      global.clearTimeout(reloj);

      cajon.hidden = false;
      doc.documentElement.classList.add('sin-scroll');

      // Un reflow forzado antes de prender la clase: si no, el navegador
      // agrupa los dos cambios y el cajón aparece de golpe en vez de entrar.
      void cajon.offsetWidth;

      cajon.setAttribute('data-abierto', 'true');
      abridor.setAttribute('aria-expanded', 'true');
      abierto = true;

      // El foco al contenedor del diálogo y no al primer botón: así el
      // lector de pantalla anuncia "Menú, diálogo" antes de leer el ítem.
      if (panel) {
        panel.focus();
      }
    }

    function cerrar() {
      if (!abierto) {
        return;
      }

      cajon.removeAttribute('data-abierto');
      abridor.setAttribute('aria-expanded', 'false');
      doc.documentElement.classList.remove('sin-scroll');
      abierto = false;

      // Primero el foco y después el hidden: al revés, el foco se pierde
      // en el <body> y quien navega con teclado vuelve a empezar de arriba.
      abridor.focus();

      global.clearTimeout(reloj);
      reloj = global.setTimeout(function () {
        cajon.hidden = true;
      }, menosMovimiento() ? 0 : DURACION_CAJON);
    }

    abridor.addEventListener('click', function () {
      if (abierto) {
        cerrar();
      } else {
        abrir();
      }
    });

    Array.prototype.forEach.call(cajon.querySelectorAll('[data-cajon-cerrar]'), function (nodo) {
      nodo.addEventListener('click', cerrar);
    });

    /* Foco atrapado. Acá sí corresponde: mientras el cajón está abierto no
       hay nada más en pantalla, y dejar que el Tab se escape al contenido
       de atrás es mandar al usuario a un lugar que no puede ver. */
    cajon.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' || evento.key === 'Esc') {
        evento.preventDefault();
        cerrar();

        return;
      }

      if (evento.key !== 'Tab' || !panel) {
        return;
      }

      var lista = enfocables(panel);

      if (!lista.length) {
        evento.preventDefault();
        panel.focus();

        return;
      }

      var primero = lista[0];
      var ultimo = lista[lista.length - 1];

      if (evento.shiftKey && (doc.activeElement === primero || doc.activeElement === panel)) {
        evento.preventDefault();
        ultimo.focus();
      } else if (!evento.shiftKey && doc.activeElement === ultimo) {
        evento.preventDefault();
        primero.focus();
      }
    });

    // Acordeón de PRODUCTOS. Dos niveles: la categoría es un enlace que
    // navega, no una tercera pantalla.
    var acordeon = cajon.querySelector('[data-cajon-acordeon]');

    if (acordeon) {
      var sublista = doc.getElementById(acordeon.getAttribute('aria-controls'));

      acordeon.addEventListener('click', function () {
        var desplegado = acordeon.getAttribute('aria-expanded') === 'true';

        acordeon.setAttribute('aria-expanded', desplegado ? 'false' : 'true');

        if (sublista) {
          sublista.hidden = desplegado;
        }
      });
    }

    // Si la ventana crece hasta el escritorio, el cajón ya no se ve: se
    // cierra para no dejar el scroll del cuerpo bloqueado.
    var escritorio = consulta('(min-width: 1280px)');

    escucharMedida(escritorio, function (evento) {
      if (evento.matches && abierto) {
        cerrar();
      }
    });
  }

  /* ==========================================================
     3. Buscador plegable de la barra de celular
     ========================================================== */

  function iniciarBusqueda() {
    var cabecera = doc.querySelector('[data-cabecera]');

    if (!cabecera) {
      return;
    }

    var boton = cabecera.querySelector('[data-busqueda-abrir]');
    var cerrarBoton = cabecera.querySelector('[data-busqueda-cerrar]');
    var campo = cabecera.querySelector('#buscador-q');

    if (!boton || !campo) {
      return;
    }

    function abrir() {
      cabecera.setAttribute('data-buscando', 'true');
      boton.setAttribute('aria-expanded', 'true');
      campo.focus();
      campo.select();
    }

    function cerrar() {
      cabecera.removeAttribute('data-buscando');
      boton.setAttribute('aria-expanded', 'false');
      boton.focus();
    }

    boton.addEventListener('click', abrir);

    if (cerrarBoton) {
      cerrarBoton.addEventListener('click', cerrar);
    }

    campo.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' || evento.key === 'Esc') {
        evento.preventDefault();
        cerrar();
      }
    });

    // En escritorio el buscador está siempre abierto: el estado "buscando"
    // no significa nada ahí y no puede quedar pegado al agrandar la ventana.
    var escritorio = consulta('(min-width: 1280px)');

    escucharMedida(escritorio, function (evento) {
      if (evento.matches) {
        cabecera.removeAttribute('data-buscando');
        boton.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ========================================================== */

  function iniciar() {
    iniciarMega();
    iniciarCajon();
    iniciarBusqueda();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
}(window));
