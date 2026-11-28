<?php
$raiz = dirname(__DIR__);
require_once $raiz . '/ElementosVistaBasica/util/BaseDatos/conexion_mysql.php';
putenv('DB_NAME_MODULO=vista_previa');
$pdo = conexionVistaPrevia();
$pdo->exec("INSERT INTO inmuebles VALUES (3, 2, 'Inmueble empresa dos', 'America/Lima', 1, 0)");
$pdo->exec("INSERT INTO alojamientos VALUES (4, 2, 3, 'Alojamiento empresa dos', 1, 1, 0)");
$construirMenu = require $raiz . '/ElementosVistaBasica/Modulos/GESTALO/menuLateral.php';
$_SESSION['empresa_id'] = 999;

$modulo = ['idModulo' => 1, 'nombreModulo' => 'GESTALO', 'idEmpresa' => 1, 'multiempresa' => true];
$primera = $construirMenu($modulo, 'OpenDoors');
$modulo['idEmpresa'] = 2;
$segunda = $construirMenu($modulo, 'OpenDoors');
$primeraOtraVez = $construirMenu(array_replace($modulo, ['idEmpresa' => 1]), 'OpenDoors');
$disponiblesUna = $primera['principal'][2][1][1][0][1];
$disponiblesDos = $segunda['principal'][2][1][1][0][1];
if ($disponiblesUna[0][0] !== 'Casa de ejemplo'
    || count($disponiblesDos) !== 1
    || $disponiblesDos[0][0] !== 'Inmueble empresa dos'
    || $disponiblesDos[0][1][0][0] !== 'Alojamiento empresa dos'
    || $primeraOtraVez !== $primera) {
    throw new RuntimeException('Los menús de empresas distintas se mezclan.');
}
if (str_contains($disponiblesDos[0][1][0][2], 'empresa=')) {
    throw new RuntimeException('El anfitrión debe añadir la empresa en modo multiempresa.');
}
$modulo['multiempresa'] = false;
$unica = $construirMenu($modulo, 'OpenDoors');
if (!str_contains($unica['principal'][2][1][1][0][1][0][1][0][2], '&empresa=2')) {
    throw new RuntimeException('El enlace de empresa única no conserva la empresa.');
}
echo "Pruebas de menús multiempresa: correctas.\n";
