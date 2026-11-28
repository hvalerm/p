<?php

function getGestaloPDO(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $nombreBase = getenv('DB_NAME_MODULO');
    if ($nombreBase === false || trim($nombreBase) === '') {
        throw new RuntimeException('Falta configurar DB_NAME_MODULO.');
    }

    require_once __DIR__ . '/../../../../util/BaseDatos/conexion_mysql.php';
    return $pdo = crearConexionPDO($nombreBase, '+00:00');
}
