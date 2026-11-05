<?php
// Verificación de la sesión del usuario.
// Uso: require_once __DIR__ . '/../util/verificar.php'; requerirSesion();

const SESION_INACTIVIDAD_MAX = 1800; // 30 minutos
const URL_LOGIN = '/OpenDoors_V2/login.php';
require_once __DIR__ . '/seguridad.php';

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => esHttps(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function haySesionActiva(): bool
{
    iniciarSesionSegura();

    if (empty($_SESSION['usuario_id'])) {
        return false;
    }

    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > SESION_INACTIVIDAD_MAX) {
        cerrarSesion();
        return false;
    }
    $_SESSION['ultima_actividad'] = $ahora;

    // Evita el robo de sesión cambiando de navegador
    $huella = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!isset($_SESSION['huella'])) {
        $_SESSION['huella'] = $huella;
    } elseif (!hash_equals($_SESSION['huella'], $huella)) {
        cerrarSesion();
        return false;
    }

    return true;
}

function requerirSesion(): void
{
    if (!haySesionActiva()) {
        header('Location: ' . URL_LOGIN . '?expirada=1');
        exit;
    }
}

function usuarioActual(): ?array
{
    if (!haySesionActiva()) {
        return null;
    }
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['nombre'] ?? '',
    ];
}

function cerrarSesion(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
