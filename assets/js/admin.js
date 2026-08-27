/* ============================================================
   admin.js — Rastro Fitness
   Las tres cosas del panel que no se pueden resolver sin
   JavaScript. Todo lo demás funciona sin este archivo, a
   propósito: el panel es la herramienta de trabajo del cliente
   y no puede depender de que un script cargue bien.

   1. Confirmar antes de una acción destructiva.
   2. Sumar y quitar filas en las listas repetibles
      (especificaciones, imágenes).
   3. Avisar si se va a perder lo que se escribió.
   ============================================================ */

(function (global) {
  'use strict';

  var doc = document;

  /* --- 1. Confirmar lo destructivo --------------------------
     El texto lo pone el marcado en data-confirmar, no este
     archivo: quien escribe la pantalla sabe qué se está por
     borrar y puede decirlo con precisión. */

  doc.addEventListener('submit', function (evento) {
    var form = evento.target;
    var pregunta = form.getAttribute && form.getAttribute('data-confirmar');

    if (pregunta && !global.confirm(pregunta)) {
      evento.preventDefault();
      return;
    }

    // Un formulario que ya se envió no se vuelve a enviar: en una
    // conexión lenta, dos clics son dos productos iguales.
    if (form.dataset && form.dataset.enviando === '1') {
      evento.preventDefault();
      return;
    }

    if (form.dataset) {
      form.dataset.enviando = '1';
      formSucio = false;
    }
  });

  /* --- 2. Listas repetibles ---------------------------------
     Una lista de pares etiqueta/valor —las especificaciones del
     producto— o de rutas de imagen. La primera fila del marcado
     es el molde: se clona, se le vacían los campos y se le
     renumeran los name[] para que PHP los reciba en orden. */

  function filas(lista) {
    return Array.prototype.slice.call(lista.querySelectorAll('.repetible__fila'));
  }

  function renumerar(lista) {
    filas(lista).forEach(function (fila, i) {
      Array.prototype.forEach.call(fila.querySelectorAll('[name]'), function (campo) {
        campo.name = campo.name.replace(/\[\d*\]/, '[' + i + ']');
      });
    });
  }

  doc.addEventListener('click', function (evento) {
    var destino = evento.target;

    var sumar = destino.closest && destino.closest('[data-repetible-sumar]');

    if (sumar) {
      evento.preventDefault();

      var lista = doc.getElementById(sumar.getAttribute('data-repetible-sumar'));

      if (!lista) {
        return;
      }

      var existentes = filas(lista);
      var molde = existentes[existentes.length - 1];

      if (!molde) {
        return;
      }

      var copia = molde.cloneNode(true);

      Array.prototype.forEach.call(copia.querySelectorAll('input, textarea'), function (campo) {
        campo.value = '';
        campo.removeAttribute('id');
      });

      lista.appendChild(copia);
      renumerar(lista);

      var primero = copia.querySelector('input, textarea');
      if (primero) {
        primero.focus();
      }

      return;
    }

    var quitar = destino.closest && destino.closest('[data-repetible-quitar]');

    if (quitar) {
      evento.preventDefault();

      var fila = quitar.closest('.repetible__fila');
      var contenedor = fila && fila.parentNode;

      if (!fila || !contenedor) {
        return;
      }

      // Nunca se queda sin filas: la última se vacía en vez de
      // borrarse, si no queda sin molde para clonar.
      if (filas(contenedor).length === 1) {
        Array.prototype.forEach.call(fila.querySelectorAll('input, textarea'), function (campo) {
          campo.value = '';
        });
        return;
      }

      contenedor.removeChild(fila);
      renumerar(contenedor);
    }
  });

  /* --- 3. No perder lo escrito ------------------------------
     Cargar un producto son veinte campos. Irse de la pantalla
     por error y perderlos es la clase de cosa que hace que
     alguien deje de usar el panel. */

  var formSucio = false;

  doc.addEventListener('input', function (evento) {
    if (evento.target.closest && evento.target.closest('form[data-avisar-cambios]')) {
      formSucio = true;
    }
  });

  global.addEventListener('beforeunload', function (evento) {
    if (!formSucio) {
      return;
    }

    // Los navegadores ignoran el texto y muestran el suyo; lo que
    // importa es que preventDefault dispare el aviso.
    evento.preventDefault();
    evento.returnValue = '';
  });
}(window));
