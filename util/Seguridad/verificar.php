<?php
require_once __DIR__ . '/../BaseDatos/db.php';

if (PHP_SAPI !== 'cli' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    exit('Esta vista requiere una conexión HTTPS.');
}

$nombreWeb = 'OpenDoors';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_name('OPENDOORS_VISTA');
        session_set_cookie_params(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function empresaUnicaCuenta(PDO $pdo, int $idCuenta): array
{
    $stmt = $pdo->prepare(
        'SELECT ec.idEmpresa AS id_empresa, e.nombreEmpresa AS nombre_empresa,
                e.licenciaExpira AS licencia_expira,
                e.habilitado AS empresa_habilitada, c.habilitado AS cuenta_habilitada
         FROM empresas_cuentas ec
         INNER JOIN empresas e ON e.idEmpresa = ec.idEmpresa
         INNER JOIN cuentas c ON c.idCuenta = ec.idCuenta
         WHERE ec.idCuenta = ? ORDER BY ec.idEmpresa LIMIT 2'
    );
    $stmt->execute([$idCuenta]);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($empresas) !== 1 || !$empresas[0]['cuenta_habilitada'] || !$empresas[0]['empresa_habilitada']) {
        throw new DomainException('La cuenta no tiene una única empresa habilitada.');
    }
    return $empresas[0];
}

function cerrarSesion(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function haySesionActiva(): bool
{
    iniciarSesionSegura();
    if (empty($_SESSION['usuario_id'])) {
        return false;
    }
    if (!isset($_SESSION['ultima_actividad'], $_SESSION['huella'])
        || time() - $_SESSION['ultima_actividad'] > 1800
        || !hash_equals($_SESSION['huella'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        cerrarSesion();
        return false;
    }
    try {
        $empresa = empresaUnicaCuenta(getPDO(), (int) $_SESSION['usuario_id']);
    } catch (DomainException $e) {
        error_log('Sesión invalidada: ' . $e->getMessage());
        cerrarSesion();
        return false;
    } catch (PDOException | RuntimeException $e) {
        error_log('No se pudo validar la sesión: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar la sesión. Inténtalo más tarde.');
    }
    if ((int) ($_SESSION['empresa_id'] ?? 0) !== (int) $empresa['id_empresa']
        || new DateTimeImmutable($empresa['licencia_expira']) < new DateTimeImmutable()) {
        cerrarSesion();
        return false;
    }
    $_SESSION['ultima_actividad'] = time();
    return true;
}

function requerirSesion(): void
{
    if (!haySesionActiva()) {
        header('Location: /login.php');
        exit;
    }
}

function usuarioActual(): ?array
{
    if (!haySesionActiva()) {
        return null;
    }
    return ['id' => $_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? ''];
}
