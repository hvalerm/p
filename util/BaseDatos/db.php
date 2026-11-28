<?php
require_once __DIR__ . '/conexion_mysql.php';

function getPDO(): PDO
{
    static $pdo = null;
    return $pdo ??= crearConexionPDO(variableEntornoRequerida('DB_NAME_EMPRESAS'), '-05:00');
}
