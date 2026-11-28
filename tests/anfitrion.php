<?php
require_once __DIR__ . '/../util/Seguridad/verificar.php';

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE cuentas (idCuenta INTEGER, habilitado INTEGER)');
$pdo->exec('CREATE TABLE empresas (idEmpresa INTEGER, nombreEmpresa TEXT, licenciaExpira TEXT, habilitado INTEGER)');
$pdo->exec('CREATE TABLE empresas_cuentas (idCuenta INTEGER, idEmpresa INTEGER)');
$pdo->exec("INSERT INTO cuentas VALUES (1, 1), (2, 0), (3, 1), (4, 1)");
$pdo->exec("INSERT INTO empresas VALUES (1, 'Empresa', '9999-12-31', 1), (2, 'Otra', '9999-12-31', 1), (3, 'Deshabilitada', '9999-12-31', 0)");
$pdo->exec('INSERT INTO empresas_cuentas VALUES (1, 1), (2, 1), (3, 1), (3, 2), (4, 3)');

if ((int) empresaUnicaCuenta($pdo, 1)['id_empresa'] !== 1) {
    throw new RuntimeException('No reconoce la empresa habilitada.');
}
foreach ([2, 3, 4, 5] as $cuenta) {
    try {
        empresaUnicaCuenta($pdo, $cuenta);
        throw new RuntimeException('Acepta una cuenta sin una única empresa habilitada.');
    } catch (DomainException $e) {
        // El rechazo es el resultado esperado.
    }
}
echo "Pruebas de sesión y empresa: correctas.\n";
