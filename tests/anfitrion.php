<?php
require_once __DIR__ . '/../util/Seguridad/verificar.php';

$renderOriginal = getenv('RENDER');
$servidorOriginal = $_SERVER;
try {
    foreach ([
        [false, ['HTTPS' => 'on'], true],
        [false, ['HTTPS' => 'off'], false],
        [false, ['HTTP_X_FORWARDED_PROTO' => 'https'], false],
        ['true', ['HTTP_X_FORWARDED_PROTO' => 'https'], true],
        ['true', ['HTTP_X_FORWARDED_PROTO' => 'http'], false],
        ['true', [], false],
    ] as [$render, $servidor, $esperado]) {
        putenv($render === false ? 'RENDER' : 'RENDER=' . $render);
        $_SERVER = $servidor;
        if (esHttps() !== $esperado) {
            throw new RuntimeException('Detección HTTPS incorrecta.');
        }
    }
} finally {
    putenv($renderOriginal === false ? 'RENDER' : 'RENDER=' . $renderOriginal);
    $_SERVER = $servidorOriginal;
}

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

$pdo->exec("UPDATE empresas SET licenciaExpira = '2000-01-01' WHERE idEmpresa = 1");
if (count(empresasHabilitadasCuenta($pdo, 3)) !== 2
    || (int) empresaVinculadaCuenta($pdo, 3, 2)['id_empresa'] !== 2
    || (int) empresaVinculadaCuenta($pdo, 3, 1)['id_empresa'] !== 1
    || (int) empresaVinculadaCuenta($pdo, 3, 2)['id_empresa'] !== 2
    || count(empresasHabilitadasCuenta($pdo, 1)) !== 1) {
    throw new RuntimeException('La cuenta multiempresa o la licencia de empresa bloquean el acceso.');
}
foreach ([[1, 2], [2, 1], [4, 3], [5, 1]] as [$cuenta, $empresa]) {
    try {
        empresaVinculadaCuenta($pdo, $cuenta, $empresa);
        throw new RuntimeException('Acepta una empresa no autorizada.');
    } catch (DomainException $e) {
        // El rechazo es el resultado esperado.
    }
}
echo "Pruebas de cuentas multiempresa: correctas.\n";
