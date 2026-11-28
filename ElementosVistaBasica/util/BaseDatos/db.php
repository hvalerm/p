<?php
require_once __DIR__ . '/conexion_mysql.php';

function getPDO(): PDO
{
    return conexionVistaPrevia();
}
