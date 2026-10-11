<?php
declare(strict_types=1);

/** Cabecera común: portada de cuero con el nombre y la navegación. */
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=IM+Fell+English:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="papel" aria-hidden="true"></div>

<header class="cubierta">
  <div class="cubierta-in">
    <a class="marca" href="index.php" aria-label="Biblioteca, inicio">
      <svg class="marca-ico" viewBox="0 0 48 40" aria-hidden="true">
        <path d="M24 8C18 4 9 4 3 6v28c6-2 15-2 21 2 6-4 15-4 21-2V6c-6-2-15-2-21 2z" fill="#f4e6c4" stroke="#d9b25c" stroke-width="2" stroke-linejoin="round"/>
        <path d="M24 8v28" stroke="#d9b25c" stroke-width="2"/>
        <path d="M8 13c4-1 9-1 13 1M8 19c4-1 9-1 13 1M8 25c4-1 9-1 13 1M40 13c-4-1-9-1-13 1M40 19c-4-1-9-1-13 1M40 25c-4-1-9-1-13 1" stroke="#a88346" stroke-width="1.4" fill="none" stroke-linecap="round"/>
      </svg>
      <span class="marca-txt">
        <b class="oro">Biblioteca</b>
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
