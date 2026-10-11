(function () {
  'use strict';
  var html = document.documentElement;
  var reducir = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var fino = !!(window.matchMedia && window.matchMedia('(pointer: fine)').matches);
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* =====================================================================
     FORMULARIOS (crear / editar / eliminar) — genérico para las 3 pantallas
     ===================================================================== */
  function contarLibros(f) {
    var c = $('#cuenta-libros', f);
    if (!c) { return; }
    var n = $$('input[name="libros[]"]:checked', f).length;
    c.textContent = n === 0 ? 'Ningún libro seleccionado' : (n === 1 ? '1 libro seleccionado' : n + ' libros seleccionados');
  }

  function filtrarLista(input) {
    var lista = document.getElementById(input.dataset.filtroLista);
    if (!lista) { return; }
    var t = input.value.trim().toLowerCase();
    $$('.opcion', lista).forEach(function (o) { o.hidden = t !== '' && (o.dataset.texto || '').indexOf(t) === -1; });
  }

  function abrirForm(dlg, datos, editar) {
    var f = $('form', dlg);
    datos = datos || {};
    f.reset();
    $('[name=accion]', f).value = editar ? 'editar' : 'crear';
    $('[name=id]', f).value = datos.id !== undefined ? datos.id : '';
    $('[data-rol=titulo]', dlg).textContent = editar ? dlg.dataset.tEditar : dlg.dataset.tCrear;
    $('[data-rol=guardar]', dlg).textContent = editar ? dlg.dataset.bEditar : dlg.dataset.bCrear;

    $$('input, select, textarea', f).forEach(function (el) {
      if (!el.name || ['csrf', 'accion', 'id', 'q', 'page'].indexOf(el.name) !== -1) { return; }
      var k = el.name.replace('[]', '');
      if (el.type === 'checkbox') {
        el.checked = Array.isArray(datos[k]) && datos[k].map(String).indexOf(el.value) !== -1;
      } else if (datos[k] !== undefined) {
        el.value = datos[k];
      }
    });
    $$('[data-bloquear-editar]', f).forEach(function (el) { el.readOnly = !!editar; });
    $$('[data-filtro-lista]', f).forEach(filtrarLista);
    contarLibros(f);
    dlg.showModal();
    var primero = $('input[type=text]:not([readonly]), input[type=date], select', f);
    if (primero) { primero.focus(); }
  }

  function ocultarAviso(el) {
    if (!el) { return; }
    el.classList.add('oculto');
    setTimeout(function () { el.remove(); }, 420);
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) { return; }

    if (b.hasAttribute('data-abrir')) { abrirForm(document.getElementById(b.dataset.abrir), null, false); return; }

    if (b.hasAttribute('data-editar')) {
      var datos = {};
      try { datos = JSON.parse(b.dataset.datos || '{}'); } catch (err) { datos = {}; }
      abrirForm(document.getElementById(b.dataset.editar), datos, true);
      return;
    }

    if (b.hasAttribute('data-eliminar')) {
      document.getElementById('del-id').value = b.dataset.id;
      document.getElementById('del-etq').textContent = b.dataset.etiqueta;
      document.getElementById('dlg-eliminar').showModal();
      return;
    }

    if (b.hasAttribute('data-cerrar')) { b.closest('dialog').close(); return; }
    if (b.hasAttribute('data-cerrar-aviso')) { ocultarAviso(b.closest('[data-aviso]')); }
  });

  document.addEventListener('change', function (e) {
    if (e.target.name === 'libros[]') { contarLibros(e.target.closest('form')); }
  });
  document.addEventListener('input', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-filtro-lista')) { filtrarLista(e.target); }
  });

  $$('dialog').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
  });

  var ok = $('.aviso-ok[data-aviso]');
  if (ok) { setTimeout(function () { ocultarAviso(ok); }, 5000); }

  /* =====================================================================
     CHISPAS DORADAS: ráfaga al pulsar botones y estela del cursor
     ===================================================================== */
  function chispa(x, y, extra) {
    var s = document.createElement('i');
    s.className = 'chispa' + (extra && extra.rafaga ? ' chispa-b' : '');
    s.style.left = x + 'px';
    s.style.top = y + 'px';
    s.style.setProperty('--s', (extra && extra.tam ? extra.tam : 5 + Math.random() * 8) + 'px');
    s.style.setProperty('--t', (extra && extra.dur ? extra.dur : .7 + Math.random() * .6) + 's');
    s.style.setProperty('--dx', (extra && extra.dx !== undefined ? extra.dx : Math.random() * 34 - 17) + 'px');
    s.style.setProperty('--dy', (extra && extra.dy !== undefined ? extra.dy : 10 + Math.random() * 26) + 'px');
    document.body.appendChild(s);
    s.addEventListener('animationend', function () { s.remove(); });
    setTimeout(function () { if (s.parentNode) { s.remove(); } }, 2000);
    return s;
  }

  function rafaga(x, y) {
    var n = 16;
    for (var i = 0; i < n; i++) {
      var a = (Math.PI * 2 * i) / n + Math.random() * .35;
      var d = 34 + Math.random() * 44;
      chispa(x, y, { rafaga: true, tam: 6 + Math.random() * 9, dur: .6 + Math.random() * .4, dx: Math.cos(a) * d, dy: Math.sin(a) * d });
    }
  }

  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest && e.target.closest('.btn');
    if (!b || b.disabled) { return; }
    var r = b.getBoundingClientRect();
    var o = document.createElement('span');
    o.className = 'onda';
    o.style.left = (e.clientX - r.left) + 'px';
    o.style.top = (e.clientY - r.top) + 'px';
    b.appendChild(o);
    setTimeout(function () { o.remove(); }, 700);
    if (!reducir) { rafaga(e.clientX, e.clientY); }
  });

  if (!reducir && fino) {
    var ultimo = 0, vivas = 0;
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType && e.pointerType !== 'mouse') { return; }
      var t = Date.now();
      if (t - ultimo < 45) { return; }
      ultimo = t;
      if (document.querySelectorAll('.chispa').length > 26) { return; }
      chispa(e.clientX, e.clientY);
    });
  }

  /* Botones principales con efecto imán */
  if (!reducir && fino) {
    $$('.btn-oro').forEach(function (b) {
      b.addEventListener('pointermove', function (e) {
        var r = b.getBoundingClientRect();
        b.style.setProperty('--tx', ((e.clientX - r.left - r.width / 2) * .18) + 'px');
        b.style.setProperty('--ty', ((e.clientY - r.top - r.height / 2) * .28) + 'px');
      });
      b.addEventListener('pointerleave', function () {
        b.style.removeProperty('--tx');
        b.style.removeProperty('--ty');
      });
    });
  }

  /* =====================================================================
     ATMÓSFERA: polvo dorado, luz de vela, progreso y paralaje
     ===================================================================== */
  var motas = $('#motas');
  if (motas && !reducir) {
    for (var i = 0; i < 32; i++) {
      var m = document.createElement('i');
      var tam = 2 + Math.random() * 5;
      m.style.left = (Math.random() * 100) + '%';
      m.style.width = m.style.height = tam + 'px';
      m.style.animationDuration = (16 + Math.random() * 20) + 's';
      m.style.animationDelay = (-Math.random() * 30) + 's';
      m.style.setProperty('--bd', (4 + Math.random() * 5) + 's');
      motas.appendChild(m);
    }
  }

  var luz = $('.luz'), progreso = $('.progreso'), rayos = $('.rayos');
  var mx = null, my = null, pendiente = false;
  function pintar() {
    pendiente = false;
    var y = window.pageYOffset || document.documentElement.scrollTop || 0;
    var alto = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    if (progreso) { progreso.style.setProperty('--p', Math.min(1, y / alto)); }
    if (rayos && !reducir) { rayos.style.translate = '0 ' + (-y * .12) + 'px'; }
    if (luz && mx !== null) {
      luz.style.setProperty('--mx', mx + 'px');
      luz.style.setProperty('--my', my + 'px');
    }
  }
  function pedir() { if (!pendiente) { pendiente = true; window.requestAnimationFrame(pintar); } }
  window.addEventListener('scroll', pedir, { passive: true });
  window.addEventListener('resize', pedir);
  document.addEventListener('pointermove', function (e) {
    if (e.pointerType && e.pointerType !== 'mouse') { return; }
    mx = e.clientX; my = e.clientY; pedir();
  });
  pedir();

  /* =====================================================================
     TÍTULOS: letra por letra
     ===================================================================== */
  if (!reducir) {
    $$('h1').forEach(function (h) {
      var texto = h.textContent.trim();
      h.setAttribute('aria-label', texto);
      h.textContent = '';
      var l = 0;
      texto.split(/\s+/).forEach(function (palabra, idx, todas) {
        var w = document.createElement('span');
        w.className = 'palabra';
        w.setAttribute('aria-hidden', 'true');
        palabra.split('').forEach(function (ch) {
          var s = document.createElement('span');
          s.className = 'letra';
          s.style.setProperty('--l', l++);
          s.textContent = ch;
          w.appendChild(s);
        });
        h.appendChild(w);
        if (idx < todas.length - 1) { h.appendChild(document.createTextNode(' ')); }
      });
    });
  }

  /* =====================================================================
     CONTADORES, REVELADO AL DESPLAZAR E INCLINACIÓN 3D
     ===================================================================== */
  function contar(el) {
    var fin = parseInt(el.dataset.n, 10) || 0;
    if (reducir || fin === 0) { el.textContent = fin; return; }
    var t0 = null, dur = 1400;
    el.textContent = '0';
    function paso(t) {
      if (t0 === null) { t0 = t; }
      var p = Math.min((t - t0) / dur, 1);
      el.textContent = Math.round(fin * (1 - Math.pow(1 - p, 4)));
      if (p < 1) { window.requestAnimationFrame(paso); }
    }
    setTimeout(function () { window.requestAnimationFrame(paso); }, 350);
  }

  function mostrar(el) {
    el.classList.add('visto');
    $$('[data-n]', el).forEach(contar);
  }

  function arrancarRevelado() {
    var els = $$('.sr');
    if (!('IntersectionObserver' in window)) { els.forEach(mostrar); return; }
    var io = new IntersectionObserver(function (entradas) {
      var k = 0;
      entradas.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.style.setProperty('--k', k++);
          mostrar(en.target);
          io.unobserve(en.target);
        }
      });
    }, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) { io.observe(el); });
  }

  function inclinar(el) {
    el.addEventListener('pointermove', function (e) {
      if (e.pointerType && e.pointerType !== 'mouse') { return; }
      var r = el.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
      el.style.setProperty('--gx', (x * 100) + '%');
      el.style.setProperty('--gy', (y * 100) + '%');
      el.style.transform = 'perspective(900px) rotateX(' + ((.5 - y) * 9) + 'deg) rotateY(' + ((x - .5) * 11) + 'deg) translateY(-6px)';
      el.classList.add('inclinando');
    });
    el.addEventListener('pointerleave', function () {
      el.style.transform = '';
      el.classList.remove('inclinando');
    });
  }
  if (!reducir && fino) { $$('.ficha, .medallon').forEach(inclinar); }

  /* =====================================================================
     PUERTAS DE ENTRADA
     ===================================================================== */
  var portal = $('.portal');
  var yaArranco = false;
  function arrancar() {
    if (yaArranco) { return; }
    yaArranco = true;
    html.classList.add('listo');
    arrancarRevelado();
  }

  if (portal && html.classList.contains('intro')) {
    var abierto = false;
    var abrir = function () {
      if (abierto) { return; }
      abierto = true;
      portal.classList.add('abre');
      setTimeout(arrancar, 750);
      setTimeout(function () { portal.classList.add('fin'); }, 2100);
    };
    portal.addEventListener('click', abrir);
    setTimeout(abrir, 3300);
  } else {
    arrancar();
  }

  /* =====================================================================
     PASO DE HOJA entre pantallas
     ===================================================================== */
  var hoja = $('.pasahoja');
  function pasarHoja(cb) {
    if (reducir || !hoja) { cb(); return; }
    try { window.sessionStorage.setItem('pasa', '1'); } catch (err) { /* sin almacenamiento */ }
    hoja.classList.add('entra');
    setTimeout(cb, 650);
  }
  window.pasarHoja = pasarHoja;

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) { return; }
    var u;
    try { u = new URL(a.href, window.location.href); } catch (err) { return; }
    if (u.origin !== window.location.origin) { return; }
    if (u.pathname === window.location.pathname && u.search === window.location.search) { return; }
    e.preventDefault();
    pasarHoja(function () { window.location.href = u.href; });
  });

  /* =====================================================================
     REABRIR EL FORMULARIO si hubo un error de validación
     ===================================================================== */
  var r = document.getElementById('reabrir');
  var dlg = document.getElementById('dlg-form');
  if (r && dlg) {
    try {
      var d = JSON.parse(r.textContent);
      abrirForm(dlg, d, d.accion === 'editar');
    } catch (err) { /* sin acción */ }
  }

  html.classList.add('ok-js');
})();
