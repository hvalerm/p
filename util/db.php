<?php
// Conexión a MySQL en Aiven (SSL requerido)
// Zona horaria del negocio: se usa para comparar la fecha de licencia_expira
date_default_timezone_set('America/Guayaquil');

define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

define('DB_CHARSET', 'utf8mb4');
// Opcional: guarda el "CA certificate" de Aiven en util/ca.pem para verificar el servidor
define('DB_SSL_CA', __DIR__ . '/ca.pem');

/**
 * Conexión PDO (recomendada).
 */
function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (is_file(DB_SSL_CA)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        } else {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $options[PDO::MYSQL_ATTR_SSL_CA] = '';
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Conexión MySQLi (alternativa).
 */
function getMySQLi(): mysqli
{
    static $mysqli = null;
    if ($mysqli === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = mysqli_init();
        if (is_file(DB_SSL_CA)) {
            $mysqli->ssl_set(null, null, DB_SSL_CA, null, null);
            $flags = MYSQLI_CLIENT_SSL;
        } else {
            $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
        $mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, null, $flags);
        $mysqli->set_charset(DB_CHARSET);
    }
    return $mysqli;
}
