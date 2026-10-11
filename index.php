<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/plantilla.php';

$pdo = db();
const ARCHIVO = 'index.php';
const POR_PAGINA = 6;

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$accion, $volver] = inicio_post(ARCHIVO);
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'eliminar' && $id > 0) {
        $pdo->prepare('DELETE FROM prestamos WHERE id = :id')->execute([':id' => $id]);
        flash('ok', 'Préstamo N.º ' . num_ficha($id) . ' eliminado.');
        header('Location: ' . $volver);
        exit;
    }

    if ($accion === 'crear' || $accion === 'editar') {
        $fecha  = trim((string)($_POST['fecha'] ?? ''));
        $cedula = trim((string)($_POST['cedula'] ?? ''));
        $raw    = $_POST['libros'] ?? [];
        $libros = [];
        foreach ((array)$raw as $v) {
            $n = (int)$v;
            if ($n > 0) { $libros[$n] = $n; }
        }
        $libros = array_values($libros);
        $errores = [];

        if (!fecha_valida($fecha)) { $errores[] = 'La fecha del préstamo no es válida.'; }

        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE cedula = :c');
        $st->execute([':c' => $cedula]);
        if ((int)$st->fetchColumn() === 0) { $errores[] = 'Elige un usuario inscrito.'; }

        if (count($libros) < 1)       { $errores[] = 'Selecciona al menos un libro.'; }
        elseif (count($libros) > 30)  { $errores[] = 'Un préstamo admite máximo 30 libros.'; }
        else {
            $marcas = implode(',', array_fill(0, count($libros), '?'));
            $st = $pdo->prepare("SELECT COUNT(*) FROM libros WHERE id IN ($marcas)");
            $st->execute($libros);
            if ((int)$st->fetchColumn() !== count($libros)) { $errores[] = 'Alguno de los libros elegidos ya no existe.'; }
        }
        if ($accion === 'editar' && $id <= 0) { $errores[] = 'Préstamo no válido.'; }

        if ($errores) {
            fallo_formulario($errores, ['accion' => $accion, 'id' => $id, 'fecha' => $fecha, 'cedula' => $cedula, 'libros' => $libros], $volver);
        }

        try {
            $pdo->beginTransaction();
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO prestamos (fecha_prestamo, cedula) VALUES (:f, :c)')
                    ->execute([':f' => $fecha, ':c' => $cedula]);
                $pid = (int)$pdo->lastInsertId();
            } else {
                $pid = $id;
                $pdo->prepare('UPDATE prestamos SET fecha_prestamo = :f, cedula = :c WHERE id = :id')
                    ->execute([':f' => $fecha, ':c' => $cedula, ':id' => $pid]);
                $pdo->prepare('DELETE FROM prestamo_libros WHERE prestamo_id = :id')->execute([':id' => $pid]);
            }
            $ins = $pdo->prepare('INSERT INTO prestamo_libros (prestamo_id, libro_id) VALUES (:p, :l)');
            foreach ($libros as $lid) { $ins->execute([':p' => $pid, ':l' => $lid]); }
            $pdo->commit();
            flash('ok', $accion === 'crear'
                ? 'Préstamo N.º ' . num_ficha($pid) . ' registrado con ' . plural(count($libros), 'libro', 'libros') . '.'
                : 'Préstamo N.º ' . num_ficha($pid) . ' actualizado.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[biblioteca] ' . $e->getMessage());
            flash('error', 'No se pudo guardar el préstamo. Inténtalo de nuevo.');
        }
        header('Location: ' . $volver);
        exit;
    }

    header('Location: ' . $volver);
    exit;
}

/* ---------- Consulta (GET) ---------- */
$filtros = params_lista($_GET);
$q = $filtros['q'] ?? '';
$pagina = max(1, (int)($filtros['page'] ?? 1));

$where = '';
$bind = [];
if ($q !== '') {
    $cond = 'u.nombre LIKE :q1 OR u.cedula LIKE :q2
             OR EXISTS (SELECT 1 FROM prestamo_libros pl JOIN libros l ON l.id = pl.libro_id
                        WHERE pl.prestamo_id = p.id AND (l.titulo LIKE :q3 OR l.autor LIKE :q4 OR l.isbn LIKE :q5))';
    $like = '%' . addcslashes($q, '\\%_') . '%';
    $bind = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like,
             ':q5' => '%' . addcslashes(preg_replace('/[\s\-]+/', '', $q), '\\%_') . '%'];
    $idq = ltrim($q, '#');
    if (ctype_digit($idq) && strlen($idq) <= 9) {
        $cond .= ' OR p.id = :qid';
        $bind[':qid'] = (int)$idq;
    }
    $where = "WHERE ($cond)";
}

$st = $pdo->prepare("SELECT COUNT(*) FROM prestamos p JOIN usuarios u ON u.cedula = p.cedula $where");
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / POR_PAGINA));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare("SELECT p.id, p.fecha_prestamo, p.cedula, u.nombre, u.telefono
                     FROM prestamos p JOIN usuarios u ON u.cedula = p.cedula
                     $where ORDER BY p.fecha_prestamo DESC, p.id DESC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$prestamos = $st->fetchAll();

$librosPorPrestamo = [];
if ($prestamos) {
    $ids = array_map(fn($p) => (int)$p['id'], $prestamos);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT pl.prestamo_id, l.id, l.titulo, l.autor, l.isbn
                         FROM prestamo_libros pl JOIN libros l ON l.id = pl.libro_id
                         WHERE pl.prestamo_id IN ($marcas) ORDER BY l.titulo");
    $st->execute($ids);
    foreach ($st->fetchAll() as $fila) { $librosPorPrestamo[(int)$fila['prestamo_id']][] = $fila; }
}

$totalGeneral = (int)$pdo->query('SELECT COUNT(*) FROM prestamos')->fetchColumn();
$usuarios = $pdo->query('SELECT cedula, nombre FROM usuarios ORDER BY nombre')->fetchAll();
$catalogo = $pdo->query('SELECT id, titulo, autor, isbn FROM libros ORDER BY titulo')->fetchAll();
$faltan = !$usuarios || !$catalogo;

encabezado('prestamos', 'Préstamos');
?>
<div class="encabezado-seccion reveal" style="--i:0">
  <div>
    <h1>Libro de préstamos</h1>
    <p class="sub"><?= plural($totalGeneral, 'préstamo registrado', 'préstamos registrados') ?>,
      <?= plural(count($catalogo), 'libro', 'libros') ?> en catálogo y
      <?= plural(count($usuarios), 'lector inscrito', 'lectores inscritos') ?>.</p>
  </div>
  <button class="btn btn-oro" type="button" data-abrir="dlg-form">Registrar préstamo</button>
</div>
<div class="filete abre-filete" aria-hidden="true"></div>

<section class="medallones sr" aria-label="Resumen de la biblioteca">
  <?php foreach ([[$totalGeneral, 'Préstamos'], [count($catalogo), 'Libros'], [count($usuarios), 'Lectores']] as $mi => [$mn, $mt]): ?>
    <article class="medallon" style="--i:<?= $mi ?>">
      <div class="disco">
        <svg class="aro" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="48" fill="none" stroke="#b8862b" stroke-width="1.400" stroke-dasharray="2 5" stroke-linecap="round"/></svg>
        <svg class="aro aro2" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="48" fill="none" stroke="#c99a3a" stroke-width="1.600" stroke-dasharray="16 7 3 7" stroke-linecap="round"/></svg>
        <b class="dorado" data-n="<?= (int)$mn ?>"><?= (int)$mn ?></b>
      </div>
      <span class="rotulo"><?= h($mt) ?></span>
    </article>
  <?php endforeach; ?>
</section>

<?php estanteria($catalogo); ?>

<form class="buscar reveal" style="--i:2" method="get" action="<?= ARCHIVO ?>" role="search">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por lector, cédula, libro, autor o N.º de préstamo" aria-label="Buscar préstamos">
  <button class="btn btn-cuero" type="submit">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn btn-tinta" href="<?= ARCHIVO ?>">Limpiar</a><?php endif; ?>
</form>

<?php if (!$prestamos): ?>
  <div class="vacio reveal" style="--i:3">
    <?= ilustracion_vacia() ?>
    <?php if ($q !== ''): ?>
      <p>Ningún préstamo coincide con "<?= h($q) ?>".</p>
    <?php elseif ($faltan): ?>
      <p>Para registrar un préstamo primero necesitas
        <?= !$usuarios ? '<a href="usuarios.php">inscribir un usuario</a>' : '' ?><?= (!$usuarios && !$catalogo) ? ' y ' : '' ?><?= !$catalogo ? '<a href="libros.php">agregar un libro</a>' : '' ?>.</p>
    <?php else: ?>
      <p>El libro de préstamos está en blanco. Registra el primero.</p>
      <button class="btn btn-oro" type="button" data-abrir="dlg-form">Registrar préstamo</button>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="fichero">
    <?php foreach ($prestamos as $n => $p):
        $ls = $librosPorPrestamo[(int)$p['id']] ?? [];
        $datos = ['id' => (int)$p['id'], 'fecha' => $p['fecha_prestamo'], 'cedula' => $p['cedula'],
                  'libros' => array_map(fn($l) => (int)$l['id'], $ls)];
    ?>
      <article class="ficha sr" style="--i:<?= $n ?>">
        <span class="brillo-3d" aria-hidden="true"></span>
        <header class="ficha-cab">
          <h2>N.º <?= num_ficha((int)$p['id']) ?></h2>
          <time datetime="<?= h($p['fecha_prestamo']) ?>"><?= h(fecha_larga($p['fecha_prestamo'])) ?></time>
        </header>
        <div class="prestatario">
          <span class="rotulo">Prestatario</span>
          <strong><?= h($p['nombre']) ?></strong>
          <span class="datos">C.C. <?= h($p['cedula']) ?> · Tel. <?= h($p['telefono']) ?></span>
        </div>
        <span class="rotulo"><?= plural(count($ls), 'libro prestado', 'libros prestados') ?></span>
        <ol class="renglones">
          <?php foreach ($ls as $l): ?>
            <li>
              <span class="tit"><?= h($l['titulo']) ?></span>
              <span class="aut">de <?= h($l['autor']) ?></span>
              <span class="isbn">ISBN <?= h($l['isbn']) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="sello" aria-hidden="true"><b>B</b></div>
        <div class="ficha-pie">
          <button class="btn btn-cuero btn-chico" type="button" data-editar="dlg-form" data-datos="<?= h(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">Editar</button>
          <button class="btn btn-vino btn-chico" type="button" data-eliminar data-id="<?= (int)$p['id'] ?>"
                  data-etiqueta="el préstamo N.º <?= num_ficha((int)$p['id']) ?> de <?= h($p['nombre']) ?>">Eliminar</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="pie-lista">
    <span class="conteo">Mostrando <?= $offset + 1 ?>–<?= $offset + count($prestamos) ?> de <?= $total ?></span>
    <?= paginacion(ARCHIVO, $filtros, $pagina, $totalPaginas) ?>
  </div>
<?php endif; ?>

<dialog id="dlg-form" class="hoja hoja-ancha" aria-labelledby="dlg-titulo"
        data-t-crear="Registrar préstamo" data-t-editar="Editar préstamo" data-b-crear="Registrar préstamo" data-b-editar="Guardar cambios">
  <form method="post" action="<?= ARCHIVO ?>" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="id" value="">
    <?= campos_filtros($filtros) ?>
    <h2 id="dlg-titulo" data-rol="titulo">Registrar préstamo</h2>

    <?php if ($faltan): ?>
      <p class="nota-hoja">Antes de registrar un préstamo necesitas
        <?= !$usuarios ? '<a href="usuarios.php">inscribir al menos un usuario</a>' : '' ?><?= (!$usuarios && !$catalogo) ? ' y ' : '' ?><?= !$catalogo ? '<a href="libros.php">agregar al menos un libro</a>' : '' ?>.</p>
    <?php endif; ?>

    <div class="fila">
      <label class="campo"><span>Fecha del préstamo</span>
        <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
      </label>
      <label class="campo"><span>Usuario (cédula)</span>
        <select name="cedula" required>
          <option value="">Elige un lector…</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= h($u['cedula']) ?>"><?= h($u['nombre']) ?> — C.C. <?= h($u['cedula']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="campo">
      <span>Libros del préstamo</span>
      <input type="search" class="filtro-libros" data-filtro-lista="lista-libros" placeholder="Filtrar la lista por título, autor o ISBN" aria-label="Filtrar libros">
      <div class="lista-libros" id="lista-libros" role="group" aria-label="Libros disponibles">
        <?php foreach ($catalogo as $l): ?>
          <label class="opcion" data-texto="<?= h(mb_strtolower_seguro($l['titulo'] . ' ' . $l['autor'] . ' ' . $l['isbn'])) ?>">
            <input type="checkbox" name="libros[]" value="<?= (int)$l['id'] ?>">
            <span class="marca-op" aria-hidden="true"></span>
            <span class="op-txt"><b><?= h($l['titulo']) ?></b><i><?= h($l['autor']) ?> · ISBN <?= h($l['isbn']) ?></i></span>
          </label>
        <?php endforeach; ?>
        <?php if (!$catalogo): ?><p class="vacio-lista">El catálogo está vacío.</p><?php endif; ?>
      </div>
      <small id="cuenta-libros" aria-live="polite">Ningún libro seleccionado</small>
    </div>

    <div class="botones">
      <button type="button" class="btn btn-tinta" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn-oro" data-rol="guardar" <?= $faltan ? 'disabled' : '' ?>>Registrar préstamo</button>
    </div>
  </form>
</dialog>
<?php pie(ARCHIVO, $filtros);

function mb_strtolower_seguro(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}
