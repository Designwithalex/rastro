/* ============================================================
   carrusel.js — Rastro Fitness
   El carrusel de placas del hero. Sin dependencias.

   LA PISTA YA FUNCIONA SIN ESTE ARCHIVO. En el CSS es un contenedor
   con overflow-x y scroll-snap: se arrastra con el dedo, se recorre
   con la rueda y con el teclado, y frena centrada en cada placa. Eso
   pasa aunque el JavaScript no llegue nunca.

   Lo que agrega este archivo es lo que el navegador no da solo:

     1. las flechas y los puntos, que arrancan `hidden` porque sin JS
        serían botones que no hacen nada (mismo criterio que la
        hamburguesa en sin-js.css);
     2. el avance solo, que se frena con el mouse encima, con el foco
        adentro, con la pestaña en segundo plano y con el dedo tocando;
     3. el punto activo, que se lee del scroll y no de una variable —
        así el estado no se desincroniza cuando alguien arrastra la
        pista a mano en vez de tocar una flecha.

   POR QUÉ SE LEE EL SCROLL Y NO SE GUARDA UN ÍNDICE
   Un índice propio tiene dos fuentes de verdad: la que dice el script
   y la que muestra la pantalla. Con scroll-snap el usuario puede mover
   la pista sin avisar, y ahí las dos se separan. La posición del
   scroll es el único dato que siempre es cierto.
   ============================================================ */

(function (global) {
  'use strict';

  var doc = global.document;

  /* Cada cuánto avanza solo. Siete segundos es lo que tarda en leerse
     una placa: es una pieza gráfica con un titular y un llamado, no un
     párrafo. Menos que eso obliga a correr para leerla. */
  var INTERVALO = 7000;

  /* Después de tocar una flecha o arrastrar, el avance automático se
     toma una vuelta larga antes de retomar. Si retomara al toque, la
     placa que la persona acaba de elegir se le iría de la pantalla. */
  var ESPERA_TRAS_GESTO = 12000;

  /* El scroll no cae en el pixel exacto: se redondea. */
  var TOLERANCIA = 4;

  function consulta(medida) {
    return global.matchMedia ? global.matchMedia(medida) : null;
  }

  function menosMovimiento() {
    var mq = consulta('(prefers-reduced-motion: reduce)');

    return !!(mq && mq.matches);
  }

  /* ==========================================================
     Un carrusel
     ========================================================== */

  function iniciarCarrusel(raiz) {
    var pista = raiz.querySelector('[data-carrusel-pista]');
    var controles = raiz.querySelector('[data-carrusel-controles]');

    if (!pista) {
      return;
    }

    var placas = Array.prototype.slice.call(pista.children);

    if (placas.length < 2) {
      return;
    }

    var anterior = raiz.querySelector('[data-carrusel-anterior]');
    var siguiente = raiz.querySelector('[data-carrusel-siguiente]');
    var puntos = Array.prototype.slice.call(
      raiz.querySelectorAll('[data-carrusel-ir]')
    );

    var reloj = null;
    var despertar = null;
    var indice = 0;

    /* --- Dónde está parada la pista -------------------------
       Se compara el centro de cada placa contra el centro de la
       pista: es la misma cuenta que hace scroll-snap: center, así
       que el punto que se prende es el que de verdad está al medio,
       incluso a mitad de un arrastre. */

    function indiceVisible() {
      var centroPista = pista.scrollLeft + pista.clientWidth / 2;
      var mejor = 0;
      var menorDistancia = Infinity;

      placas.forEach(function (placa, i) {
        var centroPlaca = placa.offsetLeft + placa.offsetWidth / 2;
        var distancia = Math.abs(centroPlaca - centroPista);

        if (distancia < menorDistancia) {
          menorDistancia = distancia;
          mejor = i;
        }
      });

      return mejor;
    }

    function finDePista() {
      return pista.scrollWidth - pista.clientWidth - TOLERANCIA;
    }

    /* --- Moverse -------------------------------------------- */

    function irA(i, suave) {
      var placa = placas[i];

      if (!placa) {
        return;
      }

      var destino = placa.offsetLeft
                  - (pista.clientWidth - placa.offsetWidth) / 2;

      if (pista.scrollTo) {
        pista.scrollTo({
          left: destino,
          behavior: suave && !menosMovimiento() ? 'smooth' : 'auto'
        });
      } else {
        pista.scrollLeft = destino;
      }
    }

    /* El avance da la vuelta al llegar al final: un carrusel que se
       queda clavado en la última placa deja de ser un carrusel. Las
       FLECHAS, en cambio, no dan la vuelta —se apagan— porque una
       flecha que salta del final al principio desorienta a quien la
       está usando para recorrer la lista. */
    function avanzar() {
      var actual = indiceVisible();

      irA(actual >= placas.length - 1 ? 0 : actual + 1, true);
    }

    /* --- Estado de los controles ---------------------------- */

    function pintar() {
      indice = indiceVisible();

      placas.forEach(function (placa, i) {
        placa.classList.toggle('carrusel__placa--activa', i === indice);
      });

      puntos.forEach(function (punto, i) {
        var activo = i === indice;

        punto.classList.toggle('carrusel__punto--activo', activo);
        punto.setAttribute('aria-current', activo ? 'true' : 'false');
      });

      if (anterior) {
        anterior.disabled = pista.scrollLeft <= TOLERANCIA;
      }

      if (siguiente) {
        siguiente.disabled = pista.scrollLeft >= finDePista();
      }
    }

    /* --- El avance automático -------------------------------
       No arranca si el sistema pide menos movimiento: una imagen
       grande que se mueve sola es exactamente lo que esa preferencia
       viene a apagar. */

    function arrancar() {
      if (reloj || menosMovimiento()) {
        return;
      }

      reloj = global.setInterval(avanzar, INTERVALO);
    }

    function frenar() {
      if (reloj) {
        global.clearInterval(reloj);
        reloj = null;
      }
    }

    /* Frena y programa la vuelta. Se llama después de cada gesto. */
    function frenarUnRato() {
      frenar();

      if (despertar) {
        global.clearTimeout(despertar);
      }

      despertar = global.setTimeout(function () {
        despertar = null;

        if (!raiz.matches(':hover') && !raiz.contains(doc.activeElement)) {
          arrancar();
        }
      }, ESPERA_TRAS_GESTO);
    }

    /* --- Cableado ------------------------------------------- */

    if (controles) {
      controles.removeAttribute('hidden');
    }

    if (anterior) {
      anterior.addEventListener('click', function () {
        irA(Math.max(0, indiceVisible() - 1), true);
        frenarUnRato();
      });
    }

    if (siguiente) {
      siguiente.addEventListener('click', function () {
        irA(Math.min(placas.length - 1, indiceVisible() + 1), true);
        frenarUnRato();
      });
    }

    puntos.forEach(function (punto) {
      punto.addEventListener('click', function () {
        irA(parseInt(punto.getAttribute('data-carrusel-ir'), 10) || 0, true);
        frenarUnRato();
      });
    });

    /* El scroll dispara muchísimo: se pinta en el siguiente cuadro y
       no en cada evento. */
    var pintando = false;

    pista.addEventListener('scroll', function () {
      if (pintando) {
        return;
      }

      pintando = true;

      global.requestAnimationFrame(function () {
        pintando = false;
        pintar();
      });
    }, { passive: true });

    /* Arrastrar con el dedo también es un gesto: pide lo mismo que
       tocar una flecha, que el carrusel deje de moverse solo. */
    pista.addEventListener('pointerdown', frenarUnRato);

    raiz.addEventListener('mouseenter', frenar);
    raiz.addEventListener('mouseleave', function () {
      if (!despertar && !raiz.contains(doc.activeElement)) {
        arrancar();
      }
    });

    raiz.addEventListener('focusin', frenar);
    raiz.addEventListener('focusout', function () {
      /* focusout salta ANTES de que el foco aterrice en su destino:
         preguntar acá por activeElement devuelve el body. Un cuadro
         después ya está donde va a estar. */
      global.requestAnimationFrame(function () {
        if (!despertar && !raiz.contains(doc.activeElement) && !raiz.matches(':hover')) {
          arrancar();
        }
      });
    });

    /* Un carrusel girando en una pestaña que nadie mira es trabajo
       tirado, y en un celular es batería. */
    doc.addEventListener('visibilitychange', function () {
      if (doc.hidden) {
        frenar();
      } else if (!despertar) {
        arrancar();
      }
    });

    /* Al cambiar el ancho, las cuentas de offsetLeft cambian y la
       pista puede quedar entre dos placas. Se la vuelve a centrar en
       la que estaba, sin animación: es un reacomodo, no un paso. */
    var reacomodo = null;

    global.addEventListener('resize', function () {
      if (reacomodo) {
        global.clearTimeout(reacomodo);
      }

      reacomodo = global.setTimeout(function () {
        irA(indice, false);
        pintar();
      }, 150);
    });

    raiz.classList.add('carrusel--vivo');
    pintar();
    arrancar();
  }

  /* ========================================================== */

  function iniciar() {
    Array.prototype.forEach.call(
      doc.querySelectorAll('[data-carrusel]'),
      iniciarCarrusel
    );
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
}(window));
