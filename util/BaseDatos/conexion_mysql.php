<?php
require_once __DIR__ . '/../db.php';
date_default_timezone_set('America/Guayaquil');

function crearConexionPDO(string $nombre, string $zonaHoraria): PDO
{
    $host = variableEntornoRequerida('DB_HOST');
    $puerto = filter_var(variableEntornoRequerida('DB_PORT'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($puerto === false) {
        throw new RuntimeException('DB_PORT debe ser un puerto válido.');
    }
    $usuario = variableEntornoRequerida('DB_USER');
    $password = variableEntornoRequerida('DB_PASS');

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $puerto, $nombre);
    $pdo = new PDO($dsn, $usuario, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_CIPHER => 'DEFAULT',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
    $pdo->exec('SET time_zone = ' . $pdo->quote($zonaHoraria));
    return $pdo;
}
