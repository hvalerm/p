<?php
// Vista previa aislada: no sustituye la autenticacion del anfitrion.
$esIpv4Privada = static function (string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $octetos = array_map('intval', explode('.', $ip));
    return $octetos[0] === 10
        || ($octetos[0] === 172 && $octetos[1] >= 16 && $octetos[1] <= 31)
        || ($octetos[0] === 192 && $octetos[1] === 168);
};

$direccionCliente = $_SERVER['REMOTE_ADDR'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$hostLocal = preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?$/iD', $host) === 1;
if (!$hostLocal && preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?::[0-9]+)?$/D', $host, $coincidencias) === 1) {
    $hostLocal = $esIpv4Privada($coincidencias[1]);
}

if (PHP_SAPI !== 'cli-server'
    || (!in_array($direccionCliente, ['127.0.0.1', '::1'], true) && !$esIpv4Privada($direccionCliente))
    || !$hostLocal
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || isset($_SERVER['HTTP_X_FORWARDED_HOST'])
    || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
    || isset($_SERVER['HTTP_FORWARDED'])) {
    http_response_code(403);
    exit('Vista previa disponible solo mediante el servidor local de prueba.');
}

$nombreWeb = 'OpenDoors';
putenv('DB_NAME_MODULO=vista_previa');

function requerirSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['empresa_id'] = 1;
    $_SESSION['usuario_id'] = 1;
}

function usuarioActual(): array
{
    return ['id' => 1, 'nombre' => 'Usuario de prueba'];
}
