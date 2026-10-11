# Biblioteca · Sistema de préstamos de libros (PHP + MySQL)

Gestiona libros, usuarios y los préstamos de una biblioteca.

- **Libro:** título, autor y código ISBN (único)
- **Usuario:** cédula, nombre y teléfono
- **Préstamo:** fecha de préstamo, cédula del usuario y los libros prestados

## Requisitos
- PHP 7.4 o superior con la extensión `pdo_mysql`
- Un servidor MySQL/MariaDB accesible

## Instalación
1. Sube la carpeta completa a tu hosting (en AlwaysData, la carpeta `www`).
2. Abre `index.php` en el navegador. Las tablas se crean solas la primera vez.

## Configuración
Las credenciales están en `config.php`. En AlwaysData el host `ftp-...` es solo para
subir archivos; la base de datos usa el host `mysql-...`. En producción conviene definir
`DB_HOST`, `DB_USER`, `DB_PASS` y `DB_NAME` como variables de entorno.

## Estructura
- `index.php`: libro de préstamos (crear, editar, eliminar, buscar)
- `libros.php`: catálogo de libros
- `usuarios.php`: registro de usuarios
- `config.php`: conexión PDO, creación de tablas, CSRF y utilidades
- `plantilla.php`: cabecera y pie comunes
- `assets/`: estilos y comportamiento

## Tablas creadas automáticamente
`usuarios`, `libros`, `prestamos` y `prestamo_libros` (relación préstamo ↔ libros),
con llaves foráneas. No se puede eliminar un usuario ni un libro que figure en un préstamo.
