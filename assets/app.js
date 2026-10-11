(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ----- Formulario de crear / editar (genérico para las tres pantallas) ----- */
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
    if (e.target.hasAttribute('data-filtro-lista')) { filtrarLista(e.target); }
  });

  /* Cerrar ventanas al pulsar fuera de la hoja */
  $$('dialog').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
  });

  /* ----- Onda dorada al pulsar un botón ----- */
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest('.btn');
    if (!b || b.disabled) { return; }
    var r = b.getBoundingClientRect();
    var o = document.createElement('span');
    o.className = 'onda';
    o.style.left = (e.clientX - r.left) + 'px';
    o.style.top = (e.clientY - r.top) + 'px';
    b.appendChild(o);
    setTimeout(function () { o.remove(); }, 700);
  });

  /* ----- Aviso ----- */
  function ocultarAviso(el) {
    if (!el) { return; }
    el.classList.add('oculto');
    setTimeout(function () { el.remove(); }, 420);
  }
  var ok = $('.aviso-ok[data-aviso]');
  if (ok) { setTimeout(function () { ocultarAviso(ok); }, 5000); }

  /* ----- Reabrir el formulario con lo escrito si hubo un error ----- */
  var r = document.getElementById('reabrir');
  var dlg = document.getElementById('dlg-form');
  if (r && dlg) {
    try {
      var d = JSON.parse(r.textContent);
      abrirForm(dlg, d, d.accion === 'editar');
    } catch (err) { /* sin acción */ }
  }
})();
