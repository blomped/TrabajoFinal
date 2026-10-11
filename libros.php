<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/plantilla.php';

$pdo = db();
const ARCHIVO = 'libros.php';
const POR_PAGINA = 10;

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$accion, $volver] = inicio_post(ARCHIVO);
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'eliminar' && $id > 0) {
        try {
            $pdo->prepare('DELETE FROM libros WHERE id = :id')->execute([':id' => $id]);
            flash('ok', 'El libro fue retirado del catálogo.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('error', 'No se puede eliminar: el libro figura en uno o más préstamos.');
            } else {
                error_log('[biblioteca] ' . $e->getMessage());
                flash('error', 'No se pudo eliminar el libro.');
            }
        }
        header('Location: ' . $volver);
        exit;
    }

    if ($accion === 'crear' || $accion === 'editar') {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $autor  = trim((string)($_POST['autor'] ?? ''));
        $isbn   = strtoupper(preg_replace('/[\s\-]+/', '', (string)($_POST['isbn'] ?? '')));
        $errores = [];

        if ($titulo === '' || mb_strlen($titulo) > 200) { $errores[] = 'El título es obligatorio (máx. 200 caracteres).'; }
        if ($autor === '' || mb_strlen($autor) > 150)   { $errores[] = 'El autor es obligatorio (máx. 150 caracteres).'; }
        if (!preg_match('/^(\d{13}|\d{9}[\dX])$/', $isbn)) { $errores[] = 'El ISBN debe tener 10 o 13 caracteres (se permiten guiones).'; }
        if ($accion === 'editar' && $id <= 0)            { $errores[] = 'Libro no válido.'; }

        if (!$errores) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM libros WHERE isbn = :i AND id <> :id');
            $st->execute([':i' => $isbn, ':id' => $accion === 'editar' ? $id : 0]);
            if ((int)$st->fetchColumn() > 0) { $errores[] = 'Ya existe un libro con ese ISBN.'; }
        }

        if ($errores) {
            fallo_formulario($errores, ['accion' => $accion, 'id' => $id, 'titulo' => $titulo, 'autor' => $autor, 'isbn' => (string)($_POST['isbn'] ?? '')], $volver);
        }

        try {
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO libros (titulo, autor, isbn) VALUES (:t, :a, :i)')
                    ->execute([':t' => $titulo, ':a' => $autor, ':i' => $isbn]);
                flash('ok', 'Libro "' . $titulo . '" agregado al catálogo.');
            } else {
                $pdo->prepare('UPDATE libros SET titulo = :t, autor = :a, isbn = :i WHERE id = :id')
                    ->execute([':t' => $titulo, ':a' => $autor, ':i' => $isbn, ':id' => $id]);
                flash('ok', 'Libro "' . $titulo . '" actualizado.');
            }
        } catch (PDOException $e) {
            error_log('[biblioteca] ' . $e->getMessage());
            flash('error', $e->getCode() === '23000' ? 'Ya existe un libro con ese ISBN.' : 'No se pudo guardar el libro.');
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
    $where = 'WHERE (l.titulo LIKE :q1 OR l.autor LIKE :q2 OR l.isbn LIKE :q3)';
    $bind[':q1'] = $bind[':q2'] = '%' . addcslashes($q, '\\%_') . '%';
    $bind[':q3'] = '%' . addcslashes(preg_replace('/[\s\-]+/', '', $q), '\\%_') . '%';
}

$st = $pdo->prepare("SELECT COUNT(*) FROM libros l $where");
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / POR_PAGINA));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare("SELECT l.*, COUNT(pl.prestamo_id) AS veces
                     FROM libros l LEFT JOIN prestamo_libros pl ON pl.libro_id = l.id
                     $where GROUP BY l.id ORDER BY l.titulo ASC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$libros = $st->fetchAll();
$totalGeneral = (int)$pdo->query('SELECT COUNT(*) FROM libros')->fetchColumn();
$estante = $pdo->query('SELECT id, titulo, autor, isbn FROM libros ORDER BY titulo LIMIT 22')->fetchAll();

encabezado('libros', 'Libros');
?>
<div class="encabezado-seccion reveal" style="--i:0">
  <div>
    <h1>Catálogo de libros</h1>
    <p class="sub"><?= plural($totalGeneral, 'volumen registrado', 'volúmenes registrados') ?> en la biblioteca.</p>
  </div>
  <button class="btn btn-oro" type="button" data-abrir="dlg-form">Agregar libro</button>
</div>
<div class="filete abre-filete" aria-hidden="true"></div>

<?php estanteria($estante, 'Del catálogo'); ?>

<form class="buscar reveal" style="--i:2" method="get" action="<?= ARCHIVO ?>" role="search">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por título, autor o ISBN" aria-label="Buscar libros">
  <button class="btn btn-cuero" type="submit">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn btn-tinta" href="<?= ARCHIVO ?>">Limpiar</a><?php endif; ?>
</form>

<?php if (!$libros): ?>
  <div class="vacio reveal" style="--i:3">
    <?= ilustracion_vacia() ?>
    <?php if ($q !== ''): ?>
      <p>Ningún libro coincide con "<?= h($q) ?>".</p>
    <?php else: ?>
      <p>El catálogo está vacío. Agrega el primer libro para comenzar.</p>
      <button class="btn btn-oro" type="button" data-abrir="dlg-form">Agregar libro</button>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="mayor reveal" style="--i:3">
    <table class="tabla">
      <thead><tr><th>Título</th><th>Autor</th><th>ISBN</th><th class="c">Préstamos</th><th class="c">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($libros as $n => $l):
          $datos = ['id' => (int)$l['id'], 'titulo' => $l['titulo'], 'autor' => $l['autor'], 'isbn' => $l['isbn']];
      ?>
        <tr class="sr" style="--r:<?= $n ?>">
          <td data-label="Título"><span class="titulo-libro"><?= h($l['titulo']) ?></span></td>
          <td data-label="Autor"><?= h($l['autor']) ?></td>
          <td data-label="ISBN"><span class="isbn"><?= h($l['isbn']) ?></span></td>
          <td data-label="Préstamos" class="c"><?= (int)$l['veces'] ?></td>
          <td data-label="Acciones" class="c">
            <div class="acciones">
              <button class="icono" type="button" title="Editar" aria-label="Editar <?= h($l['titulo']) ?>"
                      data-editar="dlg-form" data-datos="<?= h(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.500 3.500a2.100 2.100 0 0 1 3 3L7 19l-4 1 1-4 12.500-12.500z"/></svg>
              </button>
              <button class="icono icono-vino" type="button" title="Eliminar" aria-label="Eliminar <?= h($l['titulo']) ?>"
                      data-eliminar data-id="<?= (int)$l['id'] ?>" data-etiqueta="el libro «<?= h($l['titulo']) ?>»">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/></svg>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pie-lista">
    <span class="conteo">Mostrando <?= $offset + 1 ?>–<?= $offset + count($libros) ?> de <?= $total ?></span>
    <?= paginacion(ARCHIVO, $filtros, $pagina, $totalPaginas) ?>
  </div>
<?php endif; ?>

<dialog id="dlg-form" class="hoja" aria-labelledby="dlg-titulo"
        data-t-crear="Agregar libro" data-t-editar="Editar libro" data-b-crear="Guardar libro" data-b-editar="Guardar cambios">
  <form method="post" action="<?= ARCHIVO ?>" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="id" value="">
    <?= campos_filtros($filtros) ?>
    <h2 id="dlg-titulo" data-rol="titulo">Agregar libro</h2>

    <label class="campo"><span>Título</span>
      <input type="text" name="titulo" maxlength="200" required placeholder="Ej. Cien años de soledad">
    </label>
    <label class="campo"><span>Autor</span>
      <input type="text" name="autor" maxlength="150" required placeholder="Ej. Gabriel García Márquez">
    </label>
    <label class="campo"><span>Código ISBN</span>
      <input type="text" name="isbn" maxlength="17" required inputmode="text" placeholder="Ej. 978-0-307-47472-8">
      <small>10 o 13 caracteres; los guiones son opcionales.</small>
    </label>

    <div class="botones">
      <button type="button" class="btn btn-tinta" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn-oro" data-rol="guardar">Guardar libro</button>
    </div>
  </form>
</dialog>
<?php pie(ARCHIVO, $filtros);
