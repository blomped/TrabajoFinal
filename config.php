<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Bogota');

/*
 * Credenciales de la base de datos.
 * IMPORTANTE: en AlwaysData el host "ftp-..." es solo para subir archivos por FTP.
 * La base de datos MySQL se conecta con el host "mysql-...".
 * Puedes sobrescribir todo con variables de entorno: DB_HOST, DB_USER, DB_PASS, DB_NAME.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'mysql-bomboclatsito.alwaysdata.net');
define('DB_USER', getenv('DB_USER') ?: 'bomboclatsito');
define('DB_PASS', getenv('DB_PASS') ?: 'ju1337an');
define('DB_NAME', getenv('DB_NAME') ?: 'bomboclatsito_database');

/* Respaldo por si el servidor no tiene la extensión mbstring. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int
    {
        return (int)preg_match_all('/./us', $s);
    }
}

/** Escapa texto para imprimirlo de forma segura en HTML. */
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Devuelve la conexión PDO y se asegura de que las tablas existan. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        crear_tablas($pdo);
    } catch (PDOException $e) {
        error_log('[biblioteca] ' . $e->getMessage());
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
           . '<body style="font-family:Georgia,serif;padding:3rem;text-align:center;background:#eadbb5;color:#2b1a0e">'
           . '<h1>No pudimos conectar con la base de datos</h1>'
           . '<p>Revisa el host, el usuario y la clave en <code>config.php</code>.<br>'
           . 'Recuerda: la base de datos usa el host <code>mysql-…</code>, no el <code>ftp-…</code>.</p></body>';
        exit;
    }
    return $pdo;
}

/** Crea las tablas necesarias si todavía no existen. */
function crear_tablas(PDO $pdo): void
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            cedula   VARCHAR(20)  NOT NULL,
            nombre   VARCHAR(120) NOT NULL,
            telefono VARCHAR(20)  NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cedula),
            KEY idx_nombre (nombre)
        ) $opts
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS libros (
            id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(200) NOT NULL,
            autor  VARCHAR(150) NOT NULL,
            isbn   VARCHAR(13)  NOT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_isbn (isbn),
            KEY idx_titulo (titulo)
        ) $opts
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prestamos (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            fecha_prestamo DATE NOT NULL,
            cedula         VARCHAR(20) NOT NULL,
            creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fecha (fecha_prestamo),
            KEY idx_cedula (cedula),
            CONSTRAINT fk_prestamo_usuario FOREIGN KEY (cedula)
                REFERENCES usuarios (cedula) ON UPDATE CASCADE ON DELETE RESTRICT
        ) $opts
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prestamo_libros (
            prestamo_id INT UNSIGNED NOT NULL,
            libro_id    INT UNSIGNED NOT NULL,
            PRIMARY KEY (prestamo_id, libro_id),
            KEY idx_libro (libro_id),
            CONSTRAINT fk_pl_prestamo FOREIGN KEY (prestamo_id)
                REFERENCES prestamos (id) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_pl_libro FOREIGN KEY (libro_id)
                REFERENCES libros (id) ON UPDATE CASCADE ON DELETE RESTRICT
        ) $opts
    ");
}

/* ---------- Protección CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ---------- Mensajes flash ---------- */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Utilidades de lista ---------- */
function params_lista(array $src): array
{
    $p = [];
    $q = trim((string)($src['q'] ?? ''));
    $pg = (int)($src['page'] ?? 1);
    if ($q !== '') { $p['q'] = $q; }
    if ($pg > 1)   { $p['page'] = $pg; }
    return $p;
}

function url_lista(string $archivo, array $p): string
{
    return $archivo . ($p ? '?' . http_build_query($p) : '');
}

function campos_filtros(array $filtros): string
{
    $o = '';
    foreach ($filtros as $k => $v) {
        $o .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h((string)$v) . '">';
    }
    return $o;
}

/** Valida el token y devuelve [accion, urlDeRegreso]. */
function inicio_post(string $archivo): array
{
    $volver = url_lista($archivo, params_lista($_POST));
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        flash('error', 'La sesión expiró. Vuelve a intentarlo.');
        header('Location: ' . $volver);
        exit;
    }
    return [(string)($_POST['accion'] ?? ''), $volver];
}

/** Guarda el error y los datos escritos para reabrir el formulario, y redirige. */
function fallo_formulario(array $errores, array $datos, string $volver): void
{
    flash('error', implode(' ', $errores));
    $_SESSION['reabrir'] = $datos;
    header('Location: ' . $volver);
    exit;
}

function plural(int $n, string $uno, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $uno : $varios);
}

function fecha_valida(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

function fecha_larga(string $ymd): string
{
    static $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                     'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($ymd);
    if ($t === false) { return $ymd; }
    return (int)date('j', $t) . ' de ' . $meses[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function num_ficha(int $id): string
{
    return str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

/** Barra de paginación. */
function paginacion(string $archivo, array $filtros, int $pagina, int $totalPaginas): string
{
    if ($totalPaginas <= 1) { return ''; }
    $enlace = function (int $n) use ($archivo, $filtros): string {
        $f = $filtros;
        unset($f['page']);
        if ($n > 1) { $f['page'] = $n; }
        return h(url_lista($archivo, $f));
    };
    $o = '<nav class="paginas" aria-label="Paginación">';
    if ($pagina > 1) { $o .= '<a class="pag" href="' . $enlace($pagina - 1) . '" aria-label="Página anterior">‹</a>'; }
    for ($i = 1; $i <= $totalPaginas; $i++) {
        $o .= '<a class="pag' . ($i === $pagina ? ' activa' : '') . '" href="' . $enlace($i) . '"'
            . ($i === $pagina ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    if ($pagina < $totalPaginas) { $o .= '<a class="pag" href="' . $enlace($pagina + 1) . '" aria-label="Página siguiente">›</a>'; }
    return $o . '</nav>';
}
