<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/plantilla.php';

$pdo = db();
const ARCHIVO = 'usuarios.php';
const POR_PAGINA = 10;

/* ---------- Acciones (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$accion, $volver] = inicio_post(ARCHIVO);

    if ($accion === 'eliminar') {
        $ced = trim((string)($_POST['id'] ?? ''));
        try {
            $pdo->prepare('DELETE FROM usuarios WHERE cedula = :c')->execute([':c' => $ced]);
            flash('ok', 'El usuario fue retirado del registro.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('error', 'No se puede eliminar: el usuario tiene préstamos registrados.');
            } else {
                error_log('[biblioteca] ' . $e->getMessage());
                flash('error', 'No se pudo eliminar el usuario.');
            }
        }
        header('Location: ' . $volver);
        exit;
    }

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));
        // Al editar, la cédula no cambia: se toma del identificador original.
        $cedula   = $accion === 'editar' ? trim((string)($_POST['id'] ?? '')) : trim((string)($_POST['cedula'] ?? ''));
        $errores = [];

        if (!preg_match('/^[0-9]{5,15}$/', $cedula))                  { $errores[] = 'La cédula debe tener entre 5 y 15 dígitos, sin puntos ni espacios.'; }
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120)       { $errores[] = 'El nombre debe tener entre 2 y 120 caracteres.'; }
        if (!preg_match('/^[0-9+\-() ]{7,20}$/', $telefono))          { $errores[] = 'El teléfono debe tener entre 7 y 20 caracteres (dígitos, +, -, paréntesis).'; }

        if (!$errores && $accion === 'crear') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE cedula = :c');
            $st->execute([':c' => $cedula]);
            if ((int)$st->fetchColumn() > 0) { $errores[] = 'Ya existe un usuario con esa cédula.'; }
        }

        if ($errores) {
            fallo_formulario($errores, ['accion' => $accion, 'id' => $cedula, 'cedula' => $cedula, 'nombre' => $nombre, 'telefono' => $telefono], $volver);
        }

        try {
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO usuarios (cedula, nombre, telefono) VALUES (:c, :n, :t)')
                    ->execute([':c' => $cedula, ':n' => $nombre, ':t' => $telefono]);
                flash('ok', 'Usuario "' . $nombre . '" inscrito.');
            } else {
                $pdo->prepare('UPDATE usuarios SET nombre = :n, telefono = :t WHERE cedula = :c')
                    ->execute([':n' => $nombre, ':t' => $telefono, ':c' => $cedula]);
                flash('ok', 'Datos de "' . $nombre . '" actualizados.');
            }
        } catch (PDOException $e) {
            error_log('[biblioteca] ' . $e->getMessage());
            flash('error', $e->getCode() === '23000' ? 'Ya existe un usuario con esa cédula.' : 'No se pudo guardar el usuario.');
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
    $where = 'WHERE (u.nombre LIKE :q1 OR u.cedula LIKE :q2 OR u.telefono LIKE :q3)';
    $bind[':q1'] = $bind[':q2'] = $bind[':q3'] = '%' . addcslashes($q, '\\%_') . '%';
}

$st = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $where");
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / POR_PAGINA));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * POR_PAGINA;

$st = $pdo->prepare("SELECT u.*, COUNT(p.id) AS veces
                     FROM usuarios u LEFT JOIN prestamos p ON p.cedula = u.cedula
                     $where GROUP BY u.cedula ORDER BY u.nombre ASC LIMIT :lim OFFSET :off");
foreach ($bind as $k => $v) { $st->bindValue($k, $v); }
$st->bindValue(':lim', POR_PAGINA, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$usuarios = $st->fetchAll();
$totalGeneral = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

encabezado('usuarios', 'Usuarios');
?>
<div class="encabezado-seccion reveal" style="--i:0">
  <div>
    <h1>Registro de usuarios</h1>
    <p class="sub"><?= plural($totalGeneral, 'lector inscrito', 'lectores inscritos') ?> en la biblioteca.</p>
  </div>
  <button class="btn btn-oro" type="button" data-abrir="dlg-form">Inscribir usuario</button>
</div>
<div class="filete reveal" style="--i:1" aria-hidden="true"></div>

<form class="buscar reveal" style="--i:2" method="get" action="<?= ARCHIVO ?>" role="search">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar por nombre, cédula o teléfono" aria-label="Buscar usuarios">
  <button class="btn btn-cuero" type="submit">Buscar</button>
  <?php if ($q !== ''): ?><a class="btn btn-tinta" href="<?= ARCHIVO ?>">Limpiar</a><?php endif; ?>
</form>

<?php if (!$usuarios): ?>
  <div class="vacio reveal" style="--i:3">
    <?php if ($q !== ''): ?>
      <p>Ningún usuario coincide con "<?= h($q) ?>".</p>
    <?php else: ?>
      <p>Aún no hay lectores inscritos. Inscribe al primero para comenzar.</p>
      <button class="btn btn-oro" type="button" data-abrir="dlg-form">Inscribir usuario</button>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="mayor reveal" style="--i:3">
    <table class="tabla">
      <thead><tr><th>Cédula</th><th>Nombre</th><th>Teléfono</th><th class="c">Préstamos</th><th class="c">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($usuarios as $n => $u):
          $datos = ['id' => $u['cedula'], 'cedula' => $u['cedula'], 'nombre' => $u['nombre'], 'telefono' => $u['telefono']];
      ?>
        <tr style="--r:<?= $n ?>">
          <td data-label="Cédula"><span class="isbn"><?= h($u['cedula']) ?></span></td>
          <td data-label="Nombre"><span class="titulo-libro"><?= h($u['nombre']) ?></span></td>
          <td data-label="Teléfono"><?= h($u['telefono']) ?></td>
          <td data-label="Préstamos" class="c"><?= (int)$u['veces'] ?></td>
          <td data-label="Acciones" class="c">
            <div class="acciones">
              <button class="icono" type="button" title="Editar" aria-label="Editar a <?= h($u['nombre']) ?>"
                      data-editar="dlg-form" data-datos="<?= h(json_encode($datos, JSON_UNESCAPED_UNICODE)) ?>">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.500 3.500a2.100 2.100 0 0 1 3 3L7 19l-4 1 1-4 12.500-12.500z"/></svg>
              </button>
              <button class="icono icono-vino" type="button" title="Eliminar" aria-label="Eliminar a <?= h($u['nombre']) ?>"
                      data-eliminar data-id="<?= h($u['cedula']) ?>" data-etiqueta="a <?= h($u['nombre']) ?> (C.C. <?= h($u['cedula']) ?>)">
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
    <span class="conteo">Mostrando <?= $offset + 1 ?>–<?= $offset + count($usuarios) ?> de <?= $total ?></span>
    <?= paginacion(ARCHIVO, $filtros, $pagina, $totalPaginas) ?>
  </div>
<?php endif; ?>

<dialog id="dlg-form" class="hoja" aria-labelledby="dlg-titulo"
        data-t-crear="Inscribir usuario" data-t-editar="Editar usuario" data-b-crear="Inscribir" data-b-editar="Guardar cambios">
  <form method="post" action="<?= ARCHIVO ?>" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <input type="hidden" name="id" value="">
    <?= campos_filtros($filtros) ?>
    <h2 id="dlg-titulo" data-rol="titulo">Inscribir usuario</h2>

    <label class="campo"><span>Cédula</span>
      <input type="text" name="cedula" maxlength="15" required inputmode="numeric" pattern="[0-9]{5,15}" data-bloquear-editar placeholder="Solo dígitos">
      <small>No se puede cambiar una vez registrada.</small>
    </label>
    <label class="campo"><span>Nombre completo</span>
      <input type="text" name="nombre" maxlength="120" required placeholder="Ej. Ana María Restrepo">
    </label>
    <label class="campo"><span>Teléfono</span>
      <input type="text" name="telefono" maxlength="20" required inputmode="tel" placeholder="Ej. 300 123 4567">
    </label>

    <div class="botones">
      <button type="button" class="btn btn-tinta" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn-oro" data-rol="guardar">Inscribir</button>
    </div>
  </form>
</dialog>
<?php pie(ARCHIVO, $filtros);
