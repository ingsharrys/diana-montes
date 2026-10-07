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
    + '.s2-oculto{position:absolute!important;width:1px!important;height:1px!important;opacity:0!important;pointer-events:none!important;margin:0!important;padding:0!important;border:0!important}'
    + '.s2{position:relative;width:100%}'
    + '.s2-boton{width:100%;display:flex;align-items:center;gap:8px;text-align:left;border:1.5px solid var(--linea,var(--line,#E9EBF2));border-radius:12px;padding:12px 13px;font:inherit;font-size:16px;background:var(--fondo-2,#FCFBFE);color:var(--ink,#101226);cursor:pointer;min-height:48px}'
    + '.s2-vacio .s2-texto{color:#8A90A2}'
    + '.s2-texto{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
    + '.s2-flecha{color:#8A90A2;transition:transform .15s}'
    + '.s2-abierto .s2-boton,.s2-boton:focus{border-color:var(--rosa,var(--violeta,#7C3AED));outline:none;background:#fff;box-shadow:0 0 0 4px rgba(224,24,108,.10)}'
    + '.s2-abierto .s2-flecha{transform:rotate(180deg)}'
    + 'select[aria-invalid="true"] + .s2 .s2-boton{border-color:#E34948;background:#FFF7F8}'
    + '.s2-panel{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:60;background:#fff;border:1.5px solid var(--rosa,var(--violeta,#7C3AED));border-radius:12px;box-shadow:0 18px 40px rgba(16,18,38,.18);padding:8px}'
    + '.s2-panel[hidden]{display:none}'
    + '.s2-buscar{width:100%;border:1.5px solid var(--linea,var(--line,#E9EBF2));border-radius:9px;padding:10px 12px;font:inherit;font-size:16px;background:#fff;color:var(--ink,#101226)}'
    + '.s2-buscar:focus{outline:none;border-color:var(--rosa,var(--violeta,#7C3AED))}'
    + '.s2-lista{list-style:none;margin:6px 0 0;padding:0;max-height:260px;overflow-y:auto;overscroll-behavior:contain;position:relative}'
    + '.s2-grupo{font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;color:var(--violeta,#7C3AED);padding:9px 8px 3px;position:sticky;top:0;background:#fff}'
    + '.s2-op{padding:9px 10px;border-radius:8px;cursor:pointer;font-size:15px;line-height:1.3}'
    + '.s2-op.s2-activa{background:var(--rosa-soft,#F3EEFD)}'
    + '.s2-op.s2-elegida{font-weight:700}'
    + '.s2-op mark{background:#FFE58A;color:inherit;border-radius:3px;padding:0 1px}'
    + '.s2-nada{padding:12px 10px;color:#8A90A2;font-size:14px}';
  document.head.appendChild(css);

  /* ---------- Lista con buscador al estilo Select2: <select data-buscar="Texto de ayuda"> ----------
     El <select> original sigue en el formulario (se envía y se valida igual); encima se dibuja un
     botón que abre un panel con caja de búsqueda y la lista agrupada. Sin dependencias. */
  function sinTildes(t) { return String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function escapar(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var s2Abierto = null, s2Id = 0;

  function buscador(sel) {
    if (sel.dataset.vBuscar) return;
    sel.dataset.vBuscar = '1';
    var id = 's2-' + (++s2Id);
    var vacia = sel.querySelector('option[value=""]');
    var textoVacio = vacia ? vacia.textContent.trim() : 'Selecciona…';
    var permiteVacio = !!vacia && !sel.required;          // ej. "Sin zona específica"
    var items = [];                                       // [{v, t, g, n}] t = texto, g = grupo, n = texto para buscar
    Array.prototype.forEach.call(sel.querySelectorAll('option'), function (o) {
      if (o.value === '' && !permiteVacio) return;
      var g = o.parentNode.tagName === 'OPTGROUP' ? o.parentNode.label : '';
      items.push({ v: o.value, t: o.textContent.trim(), g: g, n: sinTildes(o.textContent + ' ' + g) });
    });

    var caja = document.createElement('div');
    caja.className = 's2';
    caja.innerHTML =
      '<button type="button" class="s2-boton" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="' + id + '-lista">'
      + '<span class="s2-texto"></span><span class="s2-flecha" aria-hidden="true">▾</span></button>'
      + '<div class="s2-panel" hidden>'
      + '<input type="search" class="s2-buscar" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="' + escapar(sel.dataset.buscar || 'Buscar…') + '" aria-label="' + escapar(sel.dataset.buscar || 'Buscar') + '" aria-controls="' + id + '-lista">'
      + '<ul class="s2-lista" role="listbox" id="' + id + '-lista"></ul></div>';
    sel.insertAdjacentElement('afterend', caja);
    sel.classList.add('s2-oculto');
    sel.tabIndex = -1;
    var boton = caja.querySelector('.s2-boton'), texto = caja.querySelector('.s2-texto');
    var panel = caja.querySelector('.s2-panel'), busca = caja.querySelector('.s2-buscar'), lista = caja.querySelector('.s2-lista');
    var etiqueta = sel.id && document.querySelector('label[for="' + sel.id + '"]');
    if (etiqueta) { boton.id = id + '-boton'; etiqueta.setAttribute('for', boton.id); }
    var activo = -1, visibles = [];

    function rotulo() {
      var o = sel.selectedOptions[0];
      var vacio = !o || (o.value === '' && !permiteVacio);
      texto.textContent = vacio ? textoVacio : o.textContent.trim();
      caja.classList.toggle('s2-vacio', vacio);
    }

    function marcar(t, q) {
      if (!q) return escapar(t);
      var base = sinTildes(t), i = base.indexOf(q);
      if (i < 0) return escapar(t);
      return escapar(t.slice(0, i)) + '<mark>' + escapar(t.slice(i, i + q.length)) + '</mark>' + escapar(t.slice(i + q.length));
    }

    function pintar() {
      var q = sinTildes(busca.value.trim()), html = '', grupo = null;
      visibles = items.filter(function (it) { return !q || it.n.indexOf(q) !== -1; });
      visibles.forEach(function (it, i) {
        if (it.g !== grupo) { grupo = it.g; if (grupo) html += '<li class="s2-grupo" role="presentation">' + escapar(grupo) + '</li>'; }
        html += '<li class="s2-op' + (it.v === sel.value ? ' s2-elegida' : '') + '" role="option" id="' + id + '-' + i + '" data-i="' + i + '" aria-selected="' + (it.v === sel.value) + '">' + marcar(it.t, q) + '</li>';
      });
      lista.innerHTML = html || '<li class="s2-nada">No encontramos "' + escapar(busca.value.trim()) + '". Prueba con otra palabra o elige "Otra".</li>';
      var elegida = visibles.findIndex(function (it) { return it.v === sel.value; });
      mover(q ? 0 : Math.max(elegida, 0), !q);
    }

    function mover(i, centrar) {
      var ops = lista.querySelectorAll('.s2-op');
      if (!ops.length) { activo = -1; busca.removeAttribute('aria-activedescendant'); return; }
      activo = Math.max(0, Math.min(i, ops.length - 1));
      ops.forEach(function (li) { li.classList.remove('s2-activa'); });
      var li = ops[activo];
      li.classList.add('s2-activa');
      busca.setAttribute('aria-activedescendant', li.id);
      var arriba = li.offsetTop - lista.offsetTop, abajo = arriba + li.offsetHeight;
      if (centrar) lista.scrollTop = arriba - lista.clientHeight / 2;
      else if (arriba < lista.scrollTop) lista.scrollTop = arriba - 28;
      else if (abajo > lista.scrollTop + lista.clientHeight) lista.scrollTop = abajo - lista.clientHeight;
    }

    function abrir() {
      if (s2Abierto && s2Abierto !== cerrar) s2Abierto();
      panel.hidden = false; caja.classList.add('s2-abierto'); boton.setAttribute('aria-expanded', 'true');
      busca.value = ''; pintar();
      s2Abierto = cerrar;
      busca.focus({ preventScroll: true });   // ya: así las siguientes teclas caen en la búsqueda
      // en el celular, que el panel quede a la vista por encima del teclado
      var r = caja.getBoundingClientRect();
      if (r.top > window.innerHeight * 0.35) window.scrollBy({ top: r.top - 90, behavior: 'smooth' });
    }

    function cerrar(devolverFoco) {
      if (panel.hidden) return;
      panel.hidden = true; caja.classList.remove('s2-abierto'); boton.setAttribute('aria-expanded', 'false');
      if (s2Abierto === cerrar) s2Abierto = null;
      if (devolverFoco) boton.focus();
    }

    function elegir(i) {
      var it = visibles[i];
      if (!it) return;
      sel.value = it.v;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      rotulo(); cerrar(true);
    }

    boton.addEventListener('click', function () { panel.hidden ? abrir() : cerrar(true); });
    boton.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(); }
      else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); abrir(); busca.value = e.key; pintar(); }
    });
    busca.addEventListener('input', pintar);
    busca.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); mover(activo + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mover(activo - 1); }
      else if (e.key === 'Enter') { e.preventDefault(); elegir(activo); }
      else if (e.key === 'Escape') { e.preventDefault(); cerrar(true); }
      else if (e.key === 'Tab') cerrar(false);
    });
    lista.addEventListener('mousedown', function (e) { e.preventDefault(); });   // no quitar el foco de la caja
    lista.addEventListener('click', function (e) {
      var li = e.target.closest('.s2-op');
      if (li) elegir(+li.dataset.i);
    });
    document.addEventListener('click', function (e) { if (!caja.contains(e.target)) cerrar(false); });
    sel.addEventListener('change', rotulo);                 // por si el valor cambia desde el código
    sel.addEventListener('focus', function () { boton.focus(); });
    rotulo();
  }

  function iniciar() {
    document.querySelectorAll('select[data-buscar]').forEach(buscador);
    document.querySelectorAll('form[data-validar]').forEach(preparar);
    document.querySelectorAll('[data-tipo]').forEach(filtrar);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();

  window.Validar = { campo: campo, formulario: formulario, marcar: marcar, normalizarNombre: normalizarNombre };
})();
