<?php
declare(strict_types=1);

/** Emblema de la biblioteca: anillo de texto giratorio con un libro abierto. */
function emblema(string $id, string $clase = ''): string
{
    return '<svg class="' . $clase . '" viewBox="0 0 96 96" aria-hidden="true">'
        . '<defs><path id="' . $id . '" d="M48 48m-37 0a37 37 0 1 1 74 0a37 37 0 1 1 -74 0"/></defs>'
        . '<circle cx="48" cy="48" r="45" fill="#33200f" stroke="#d9b25c" stroke-width="2"/>'
        . '<circle cx="48" cy="48" r="29.500" fill="none" stroke="#d9b25c" stroke-width="1" opacity=".7"/>'
        . '<g class="anillo-txt"><text font-family="IM Fell English, Georgia, serif" font-size="8.400" fill="#ecd08a">'
        . '<textPath href="#' . $id . '" textLength="226" lengthAdjust="spacing">EX LIBRIS • BIBLIOTECA • EX LIBRIS • BIBLIOTECA •</textPath></text></g>'
        . '<g transform="translate(26.500 30) scale(.9)">'
        . '<path d="M24 8C18 4 9 4 3 6v28c6-2 15-2 21 2 6-4 15-4 21-2V6c-6-2-15-2-21 2z" fill="#f4e6c4" stroke="#d9b25c" stroke-width="2" stroke-linejoin="round"/>'
        . '<path d="M24 8v28" stroke="#d9b25c" stroke-width="2"/>'
        . '<path d="M8 13c4-1 9-1 13 1M8 19c4-1 9-1 13 1M8 25c4-1 9-1 13 1M40 13c-4-1-9-1-13 1M40 19c-4-1-9-1-13 1M40 25c-4-1-9-1-13 1" stroke="#a88346" stroke-width="1.400" fill="none" stroke-linecap="round"/>'
        . '</g></svg>';
}

/** Libro flotante con destellos para los estados vacíos. */
function ilustracion_vacia(): string
{
    return '<svg class="libro-flota" viewBox="0 0 120 100" aria-hidden="true">'
        . '<ellipse class="sombra-libro" cx="60" cy="92" rx="34" ry="5" fill="rgba(60,30,10,.3)"/>'
        . '<g class="libro-g">'
        . '<path d="M60 20C46 10 22 10 8 15v58c14-5 38-5 52 4 14-9 38-9 52-4V15C98 10 74 10 60 20z" fill="#f4e6c4" stroke="#8a6420" stroke-width="3" stroke-linejoin="round"/>'
        . '<path d="M60 20v57" stroke="#8a6420" stroke-width="3"/>'
        . '<path d="M18 26c10-2 26-1 36 3M18 38c10-2 26-1 36 3M18 50c10-2 26-1 36 3M102 26c-10-2-26-1-36 3M102 38c-10-2-26-1-36 3M102 50c-10-2-26-1-36 3" stroke="#a88346" stroke-width="2" fill="none" stroke-linecap="round"/>'
        . '</g>'
        . '<path class="t t1" d="M98 6l2.500 6 6 2.500-6 2.500L98 23l-2.500-6-6-2.500 6-2.500z" fill="#d9a441"/>'
        . '<path class="t t2" d="M16 4l1.800 4.200 4.200 1.800-4.200 1.800L16 16l-1.800-4.200L10 10l4.200-1.800z" fill="#d9a441"/>'
        . '<path class="t t3" d="M110 44l1.600 3.800 3.800 1.600-3.800 1.600L110 55l-1.600-4-3.800-1.600 3.800-1.600z" fill="#d9a441"/>'
        . '</svg>';
}

/** Estantería con los lomos reales del catálogo. */
function estanteria(array $libros, string $titulo = 'Del catálogo'): void
{
    if (!$libros) { return; }
    $paleta = [
        ['#7a2a36', '#4d1520'], ['#34553a', '#1f3624'], ['#7a4b2a', '#4a2c18'],
        ['#2f4262', '#1b2740'], ['#a7742f', '#6f4a18'], ['#3a2d24', '#1c1510'],
    ];
    $libros = array_slice($libros, 0, 22);
    ?>
<section class="estanteria sr" aria-label="Estantería del catálogo">
  <div class="estante-cab">
    <span class="rotulo"><?= h($titulo) ?></span>
    <span class="estante-nota">Pasa el cursor sobre un lomo</span>
  </div>
  <div class="estante-fondo">
    <div class="libros-fila">
      <?php foreach ($libros as $i => $l):
          $hash = crc32($l['isbn'] . $l['titulo']);
          $c = $paleta[$hash % count($paleta)];
          $alto = 150 + (($hash >> 3) % 62);
          $ancho = 36 + (($hash >> 7) % 17);
      ?>
        <a class="lomo" href="libros.php?q=<?= rawurlencode((string)$l['isbn']) ?>"
           style="--c1:<?= $c[0] ?>;--c2:<?= $c[1] ?>;--h:<?= $alto ?>px;--w:<?= $ancho ?>px;--i:<?= $i ?>"
           aria-label="<?= h($l['titulo']) ?>, de <?= h($l['autor']) ?>">
          <span class="lomo-txt"><?= h($l['titulo']) ?></span>
          <span class="tip"><?= h($l['titulo']) ?> — <?= h($l['autor']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="tabla-estante" aria-hidden="true"></div>
</section>
<?php
}

/** Cabecera común: portada de cuero, atmósfera, puertas de entrada y navegación. */
function encabezado(string $activo, string $titulo): void
{
    $items = [
        'prestamos' => ['index.php', 'Préstamos'],
        'libros'    => ['libros.php', 'Libros'],
        'usuarios'  => ['usuarios.php', 'Usuarios'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> · Biblioteca</title>
<script>
/* Decide qué animación de llegada mostrar. Si algo fallara, todo se libera solo a los pocos segundos. */
(function(){
  var d=document.documentElement; d.classList.add('js');
  setTimeout(function(){ if(!d.classList.contains('ok-js')){ d.classList.add('listo','forzar'); } },7000);
  try{
    var s=window.sessionStorage;
    if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches){return;}
    if(s.getItem('pasa')==='1'){d.classList.add('llega'); s.removeItem('pasa'); s.setItem('intro','1');}
    else if(!s.getItem('intro')){d.classList.add('intro'); s.setItem('intro','1');}
  }catch(e){}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=IM+Fell+English:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="progreso" aria-hidden="true"></div>
<div class="papel" aria-hidden="true"></div>
<div class="rayos" aria-hidden="true"></div>
<div class="motas" id="motas" aria-hidden="true"></div>
<div class="luz" aria-hidden="true"></div>
<div class="pasahoja" aria-hidden="true"></div>

<div class="portal" aria-hidden="true">
  <div class="portal-luz"></div>
  <div class="puerta puerta-izq"><span class="puerta-marco"></span></div>
  <div class="puerta puerta-der"><span class="puerta-marco"></span></div>
  <div class="portal-centro">
    <?= emblema('anillo-portal', 'emblema') ?>
    <p class="portal-titulo dorado">Biblioteca</p>
    <p class="portal-lema">Ex libris</p>
    <p class="portal-pista">Toca para entrar</p>
  </div>
</div>

<header class="cubierta">
  <div class="cubierta-in">
    <a class="marca" href="index.php" aria-label="Biblioteca, inicio">
      <?= emblema('anillo-marca', 'crest') ?>
      <span class="marca-txt">
        <b class="oro dorado">Biblioteca</b>
        <i>Registro de préstamos y catálogo</i>
      </span>
    </a>
    <nav class="lomos" aria-label="Secciones">
      <?php foreach ($items as $clave => [$url, $texto]): ?>
        <a href="<?= $url ?>" class="<?= $clave === $activo ? 'activa' : '' ?>" <?= $clave === $activo ? 'aria-current="page"' : '' ?>><?= $texto ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="pagina">
<?php
}

/** Pie común: ventana de eliminar, avisos y scripts. */
function pie(string $archivo, array $filtros): void
{
    $flash = tomar_flash();
    $reabrir = $_SESSION['reabrir'] ?? null;
    unset($_SESSION['reabrir']);
    ?>
</main>

<footer class="colofon">
  <span class="filete corto" aria-hidden="true"></span>
  <p>Biblioteca · Registro de préstamos</p>
</footer>

<dialog id="dlg-eliminar" class="hoja hoja-chica" aria-labelledby="del-titulo">
  <form method="post" action="<?= h($archivo) ?>">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id" id="del-id" value="">
    <?= campos_filtros($filtros) ?>
    <h2 id="del-titulo">¿Eliminar este registro?</h2>
    <p>Vas a eliminar <strong id="del-etq"></strong>. Esta acción no se puede deshacer.</p>
    <div class="botones">
      <button type="button" class="btn btn-tinta" data-cerrar>Conservar</button>
      <button type="submit" class="btn btn-vino">Eliminar</button>
    </div>
  </form>
</dialog>

<?php if ($flash): ?>
  <div class="aviso aviso-<?= h($flash['tipo']) ?>" role="status" data-aviso>
    <span><?= h($flash['mensaje']) ?></span>
    <button type="button" class="aviso-cerrar" aria-label="Cerrar aviso" data-cerrar-aviso>×</button>
  </div>
<?php endif; ?>

<?php if ($reabrir): ?>
<script id="reabrir" type="application/json"><?= json_encode($reabrir, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
<?php
}
