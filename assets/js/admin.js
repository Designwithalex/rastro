/* ============================================================
   admin.js — el panel de administración.

   Tres ayudas y nada más. El panel funciona entero sin este archivo:
   son formularios HTML que hacen POST y páginas que se redibujan. Eso
   no es una limitación, es lo que hace que una herramienta que escribe
   los datos del negocio no dependa de que un script cargue bien.

     1. confirmar antes de borrar,
     2. avisar si se sale de una página con cambios sin guardar,
     3. mostrar la dirección web mientras se escribe el nombre.

   Ninguna de las tres es imprescindible. La que más se extraña es la
   primera, y su respaldo es que el servidor sólo borra por POST con
   token: nada se borra por abrir un enlace.
   ============================================================ */

(function (global) {
  'use strict';

  var doc = global.document;

  /* ==========================================================
     1. Confirmar los borrados
     ========================================================== */

  function iniciarConfirmaciones() {
    Array.prototype.forEach.call(
      doc.querySelectorAll('[data-confirmar]'),
      function (formulario) {
        formulario.addEventListener('submit', function (evento) {
          if (!global.confirm(formulario.getAttribute('data-confirmar'))) {
            evento.preventDefault();
          }
        });
      }
    );
  }

  /* ==========================================================
     2. Cambios sin guardar

     El panel guarda con un botón, no solo. Alguien que carga una
     ficha de veinte campos y toca "Productos" en el menú pierde
     todo sin que nada se lo diga. Esto es lo único que lo avisa.

     La marca se levanta en el submit: si no, guardar dispararía
     el mismo cartel que se quiere evitar.
     ========================================================== */

  function iniciarCentinela() {
    var formularios = doc.querySelectorAll('.panel-formulario');

    if (!formularios.length) {
      return;
    }

    var sucio = false;

    Array.prototype.forEach.call(formularios, function (formulario) {
      formulario.addEventListener('input', function () {
        sucio = true;
      });

      formulario.addEventListener('submit', function () {
        sucio = false;
      });
    });

    global.addEventListener('beforeunload', function (evento) {
      if (!sucio) {
        return;
      }

      /* El texto lo elige el navegador: hace años que ninguno muestra
         el que manda la página. Lo que importa es preventDefault. */
      evento.preventDefault();
      evento.returnValue = '';
    });
  }

  /* ==========================================================
     3. Eco de la dirección web

     Mientras se escribe el nombre de un producto nuevo, la ayuda de
     abajo del campo muestra cómo va a quedar la URL. Sólo en el ALTA:
     en una edición el slug ya existe y no se regenera solo, así que
     mostrar uno derivado del nombre prometería un cambio que no
     ocurre.
     ========================================================== */

  /* Misma cuenta que slug() en helpers.php. Es una copia y se sabe:
     el servidor vuelve a calcularlo y su resultado es el que manda.
     Esto es una vista previa, no la fuente de verdad. */
  function aSlug(texto) {
    return texto
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function iniciarEcoSlug() {
    var nombre = doc.getElementById('nombre');
    var campo = doc.getElementById('slug');
    var eco = doc.querySelector('[data-eco-slug]');

    if (!nombre || !campo || !eco) {
      return;
    }

    // Con slug ya cargado estamos editando: no se toca nada.
    if (campo.value.trim() !== '') {
      nombre.addEventListener('input', function () {
        eco.textContent = aSlug(campo.value) || '…';
      });

      campo.addEventListener('input', function () {
        eco.textContent = aSlug(campo.value) || '…';
      });

      return;
    }

    function pintar() {
      eco.textContent = aSlug(campo.value || nombre.value) || '…';
    }

    nombre.addEventListener('input', pintar);
    campo.addEventListener('input', pintar);
    pintar();
  }

  /* ========================================================== */

  function iniciar() {
    iniciarConfirmaciones();
    iniciarCentinela();
    iniciarEcoSlug();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
}(window));
