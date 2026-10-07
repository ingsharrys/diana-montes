/**
 * validar.js — validación de formularios en el navegador (landing y admin).
 * Las reglas son las mismas de inc/validacion.php: si cambias una, cambia la otra.
 *
 * Uso: <form data-validar> y en cada campo data-tipo="nombre|documento|celular|
 * correo|nacimiento|numero|texto". Los campos con `required` son obligatorios.
 * - Mientras se escribe, el campo no deja entrar caracteres que no corresponden.
 * - Al salir del campo o al enviar, el error aparece debajo del campo y la
 *   página se desplaza hasta el primer campo con problemas.
 * Con data-validar="manual" el formulario no se bloquea solo: el código de la
 * página llama a Validar.formulario(form) antes de enviar.
 */
(function () {
  'use strict';

  var DOC_MIN = 5, DOC_MAX = 10;
  var PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'van', 'von'];

  function normalizarNombre(v) {
    return v.replace(/\s+/g, ' ').trim().toLowerCase().split(' ').map(function (p, i) {
      if (i > 0 && PARTICULAS.indexOf(p) !== -1) return p;
      return p.charAt(0).toUpperCase() + p.slice(1);
    }).join(' ');
  }

  function soloCelular(v) {
    var d = v.replace(/\D/g, '');
    if (d.length > 10 && d.indexOf('57') === 0) d = d.slice(2); // +57 pegado
    return d.slice(0, 10);
  }

  function edad(fecha) {
    var hoy = new Date(), n = new Date(fecha + 'T00:00:00');
    var a = hoy.getFullYear() - n.getFullYear();
    var m = hoy.getMonth() - n.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < n.getDate())) a--;
    return a;
  }

  /* ---------- filtros mientras se escribe ---------- */
  var FILTROS = {
    nombre: function (v) { return v.replace(/[^\p{L}\p{M} ]/gu, '').replace(/ {2,}/g, ' ').replace(/^ /, ''); },
    documento: function (v) { return v.replace(/\D/g, '').slice(0, DOC_MAX); },
    celular: soloCelular,
    numero: function (v) { return v.replace(/\D/g, ''); },
    correo: function (v) { return v.replace(/\s/g, ''); },
    texto: function (v) { return v.replace(/[^\p{L}\p{M}\p{N} .,()\-\/#º°]/gu, '').replace(/ {2,}/g, ' '); },
    'nombre-simple': function (v) { return v.replace(/[^\p{L}\p{M} ()\/]/gu, '').replace(/ {2,}/g, ' '); },
    plantilla: function (v) { return v.toLowerCase().replace(/\s/g, '_').replace(/[^a-z0-9_]/g, ''); }
  };

  /* ---------- reglas: devuelven el mensaje de error o '' ---------- */
  var REGLAS = {
    nombre: function (v) {
      if (!/^[\p{L}\p{M} ]+$/u.test(v)) return 'El nombre solo puede tener letras y espacios (sin números ni símbolos).';
      if (v.length > 120) return 'El nombre es demasiado largo.';
      var palabras = v.split(' ').filter(function (p) { return p.length >= 2; });
      return palabras.length < 2 ? 'Escribe nombre y apellido.' : '';
    },
    documento: function (v) {
      return new RegExp('^[1-9]\\d{' + (DOC_MIN - 1) + ',' + (DOC_MAX - 1) + '}$').test(v) ? ''
        : 'El documento debe tener entre ' + DOC_MIN + ' y ' + DOC_MAX + ' dígitos, sin puntos ni letras.';
    },
    celular: function (v) { return /^3\d{9}$/.test(v) ? '' : 'El celular debe tener 10 dígitos y empezar por 3.'; },
    correo: function (v) {
      return v.length <= 150 && /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(v) ? '' : 'Escribe un correo válido, por ejemplo nombre@gmail.com.';
    },
    nacimiento: function (v, el) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(v) || isNaN(new Date(v + 'T00:00:00'))) return 'Escribe una fecha válida.';
      var e = edad(v), min = parseInt(el.dataset.edadMin || '18', 10);
      if (e < 0 || e > 110) return 'Revisa la fecha de nacimiento.';
      if (e < min) return 'La red de la campaña es para mayores de ' + min + ' años.';
      return '';
    },
    numero: function (v, el) {
      var n = parseInt(v, 10);
      if (el.dataset.min && n < +el.dataset.min) return 'El número mínimo es ' + el.dataset.min + '.';
      if (el.dataset.max && n > +el.dataset.max) return 'El número máximo es ' + el.dataset.max + '.';
      return '';
    },
    texto: function (v) { return v.trim().length < 2 ? 'Escribe al menos 2 caracteres.' : ''; },
    clave: function (v, el) {
      var min = el.minLength > 0 ? el.minLength : 6;
      if (v.length < min) return 'La clave debe tener al menos ' + min + ' caracteres.';
      if (/^(.)\1+$/.test(v)) return 'La clave no puede ser un solo carácter repetido.';
      return '';
    },
    repetir: function (v, el) {
      var otro = document.getElementById(el.dataset.igual);
      return otro && otro.value !== v ? 'Las dos claves no coinciden.' : '';
    }
  };

  function tipo(el) { return el.dataset.tipo || (el.type === 'email' ? 'correo' : ''); }

  function contenedor(el) {
    return el.closest('.campo, .field') || (el.type === 'checkbox' ? el.closest('label') : null) || el.parentNode;
  }

  function pintar(el, msg) {
    var c = contenedor(el), em;
    if (el.type === 'checkbox') {
      em = c.nextElementSibling && c.nextElementSibling.classList.contains('v-error') ? c.nextElementSibling : null;
    } else {
      em = c.querySelector('em.v-error') || c.querySelector(':scope > em');
    }
    if (msg) {
      if (!em) {
        em = document.createElement('em');
        em.className = 'v-error';
        em.id = 'err-' + (el.id || el.name) + '-' + Math.random().toString(36).slice(2, 7);
        if (el.type === 'checkbox') c.insertAdjacentElement('afterend', em); else c.appendChild(em);
      }
      em.textContent = msg;
      em.hidden = false;
      el.setAttribute('aria-invalid', 'true');
      el.setAttribute('aria-describedby', em.id || '');
      c.classList.add('has-error');
    } else {
      if (em) { em.hidden = true; em.textContent = ''; }
      el.removeAttribute('aria-invalid');
      c.classList.remove('has-error');
    }
  }

  /** Valida un campo, pinta el resultado y devuelve el mensaje ('' si está bien). */
  function campo(el) {
    if (el.disabled || el.type === 'hidden' || el.closest('[hidden]')) return '';
    var t = tipo(el), msg = '';
    if (el.type === 'checkbox') {
      if (el.required && !el.checked) msg = el.dataset.msg || 'Debes marcar esta casilla.';
    } else {
      if (t === 'nombre' && el.value) el.value = normalizarNombre(el.value);
      else if (t === 'correo') el.value = el.value.trim().toLowerCase();
      else if (t === 'texto') el.value = el.value.replace(/\s+/g, ' ').trim();
      var v = el.value;
      if (v === '') msg = el.required ? (el.dataset.msg || (el.tagName === 'SELECT' ? 'Selecciona una opción.' : 'Este campo es obligatorio.')) : '';
      else if (REGLAS[t]) msg = REGLAS[t](v, el);
    }
    pintar(el, msg);
    return msg;
  }

  function campos(form) {
    return Array.prototype.filter.call(form.elements, function (el) {
      return /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && el.type !== 'hidden' && el.type !== 'submit'
        && (el.required || tipo(el)) && !el.closest('.hp');
    });
  }

  /** Valida todo el formulario; si hay errores lleva al usuario al primero. */
  function formulario(form) {
    var primero = null;
    campos(form).forEach(function (el) { if (campo(el) && !primero) primero = el; });
    if (primero) irA(primero);
    return !primero;
  }

  function irA(el) {
    var objetivo = el.type === 'checkbox' ? contenedor(el) : el;
    objetivo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () { el.focus({ preventScroll: true }); }, 350);
  }

  /** Marca un error que llegó del servidor sobre el campo `nombre`. */
  function marcar(form, nombre, msg) {
    var el = form.elements[nombre];
    if (!el || !el.tagName) return false;
    pintar(el, msg);
    irA(el);
    return true;
  }

  /** Filtro de caracteres para un campo con data-tipo (aunque su formulario no se valide). */
  function filtrar(el) {
    if (el.dataset.vFiltro) return;
    el.dataset.vFiltro = '1';
    var t = tipo(el);
    if (t === 'documento') { el.setAttribute('inputmode', 'numeric'); el.setAttribute('maxlength', DOC_MAX); }
    if (t === 'celular') el.setAttribute('inputmode', 'tel');
    if (t === 'numero') el.setAttribute('inputmode', 'numeric');
    if (!FILTROS[t]) return;
    el.addEventListener('input', function () {
      var antes = el.value, despues = FILTROS[t](antes);
      if (antes !== despues) {
        var pos = el.selectionStart - (antes.length - despues.length);
        el.value = despues;
        try { el.setSelectionRange(Math.max(pos, 0), Math.max(pos, 0)); } catch (e) { /* email/date */ }
      }
      if (el.getAttribute('aria-invalid')) campo(el); // corrige el error mientras escribe
    });
  }

  function preparar(form) {
    form.setAttribute('novalidate', '');
    campos(form).forEach(function (el) {
      filtrar(el);
      var evento = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'date') ? 'change' : 'blur';
      el.addEventListener(evento, function () { if (el.value !== '' || el.getAttribute('aria-invalid') || el.type === 'checkbox') campo(el); });
    });
    if (form.dataset.validar !== 'manual') {
      // en fase de captura: corre antes que cualquier otro manejador del envío
      form.addEventListener('submit', function (ev) {
        if (!formulario(form)) { ev.preventDefault(); ev.stopImmediatePropagation(); }
      }, true);
    }
  }

  // Estilos mínimos para los mensajes (sirven en la landing y en el admin)
  var css = document.createElement('style');
  css.textContent = 'em.v-error{display:block;color:#B4123F;font-size:12px;font-style:normal;font-weight:600;margin-top:5px;line-height:1.4}'
    + 'em[hidden]{display:none!important}'
    + '[aria-invalid="true"]{border-color:#E34948!important;background:#FFF7F8!important}'
    + 'label.has-error{border-color:#F7C4CE!important}'
    + 'input.v-buscar{width:100%;margin-bottom:6px;font-size:15px}';
  document.head.appendChild(css);

  /* ---------- buscador para listas largas: <select data-buscar="Texto de ayuda"> ---------- */
  function sinTildes(t) { return t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }

  function buscador(sel) {
    if (sel.dataset.vBuscar) return;
    sel.dataset.vBuscar = '1';
    // copia de las opciones originales (con sus grupos) para reconstruir la lista al filtrar
    var vacia = sel.querySelector('option[value=""]');
    var vaciaTxt = vacia ? vacia.textContent : '';
    var grupos = [];
    Array.prototype.forEach.call(sel.children, function (n) {
      if (n.tagName === 'OPTGROUP') {
        grupos.push({ label: n.label, ops: Array.prototype.map.call(n.children, function (o) { return [o.value, o.textContent]; }) });
      } else if (n.value !== '') {
        grupos.push({ label: null, ops: [[n.value, n.textContent]] });
      }
    });
    var caja = document.createElement('input');
    caja.type = 'search';
    caja.className = 'v-buscar';
    caja.placeholder = sel.dataset.buscar || 'Buscar…';
    caja.setAttribute('aria-label', caja.placeholder);
    caja.autocomplete = 'off';
    sel.parentNode.insertBefore(caja, sel);

    function pintar(q) {
      var actual = sel.value, total = 0, unica = null;
      sel.innerHTML = '';
      var v = document.createElement('option'); v.value = ''; v.textContent = vaciaTxt || 'Selecciona…'; sel.appendChild(v);
      grupos.forEach(function (g) {
        var cabe = q ? sinTildes((g.label || '') + ' ').indexOf(q) !== -1 : true;
        var ops = g.ops.filter(function (o) { return cabe || sinTildes(o[1]).indexOf(q) !== -1; });
        if (!ops.length) return;
        var destino = sel;
        if (g.label) { destino = document.createElement('optgroup'); destino.label = g.label; sel.appendChild(destino); }
        ops.forEach(function (o) {
          var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1];
          destino.appendChild(op); total++; unica = o[0];
        });
      });
      if (q && !total) v.textContent = 'Sin resultados para "' + caja.value + '"';
      else if (q) v.textContent = total + ' resultado' + (total === 1 ? '' : 's') + ': elige aquí';
      else v.textContent = vaciaTxt || 'Selecciona…';
      if (q && total === 1) { sel.value = unica; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      else sel.value = Array.prototype.some.call(sel.options, function (o) { return o.value === actual; }) ? actual : '';
    }
    caja.addEventListener('input', function () { pintar(sinTildes(caja.value.trim())); });
  }

  function iniciar() {
    document.querySelectorAll('select[data-buscar]').forEach(buscador);
    document.querySelectorAll('form[data-validar]').forEach(preparar);
    document.querySelectorAll('[data-tipo]').forEach(filtrar);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();

  window.Validar = { campo: campo, formulario: formulario, marcar: marcar, normalizarNombre: normalizarNombre };
})();
