/* ============================================================
   checkout-retorno.js — Rastro Fitness
   Vacía el carrito cuando la compra se cerró.

   Se carga SOLO cuando la vista de retorno decide que
   corresponde: pago aprobado, o pedido anotado para pagar por
   transferencia. Si el pago se rechazó, la vista no lo carga, y
   el carrito queda intacto para poder reintentar sin volver a
   cargar todo.

   Quien decide es PHP, no este archivo: el estado del pago sale
   de consultarle a la API de Mercado Pago, y el navegador no
   tiene forma de saberlo.
   ============================================================ */

(function (global) {
  'use strict';

  var raiz = document.querySelector('[data-vaciar-carrito]');

  if (!raiz || !global.Carrito) {
    return;
  }

  global.Carrito.vaciar();
}(window));
