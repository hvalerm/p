<?php
// Protección contra fuerza bruta en el login.
// Cuenta los intentos fallidos por usuario (exista o no, para no revelar qué cuentas existen).
// Al llegar al máximo, se bloquea el usuario unos minutos; un inicio de sesión correcto reinicia el contador.

const LOGIN_MAX_INTENTOS = 30;
const LOGIN_BLOQUEO_MINUTOS = 15;

function claveIntentos(string $usuario): string
{
    return mb_substr(mb_strtolower(trim($usuario), 'UTF-8'), 0, 255, 'UTF-8');
}

// Segundos de bloqueo que quedan (0 si puede intentar). Limpia los bloqueos ya vencidos.
function segundosBloqueado(PDO $pdo, string $clave): int
{
    $stmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta), 0) FROM intentos_login WHERE usuario = ? AND bloqueado_hasta IS NOT NULL');
    $stmt->execute([$clave]);
    $seg = $stmt->fetchColumn();
    if ($seg === false) {
        return 0;
    }
    if ((int) $seg === 0) {
        limpiarIntentos($pdo, $clave);
    }
    return (int) $seg;
}

// Suma un intento fallido y devuelve cuántos intentos quedan antes del bloqueo.
function registrarFallo(PDO $pdo, string $clave): int
{
    $pdo->prepare(
        'INSERT INTO intentos_login (usuario, intentos) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE intentos = intentos + 1,
             bloqueado_hasta = IF(intentos >= ' . LOGIN_MAX_INTENTOS . ', DATE_ADD(NOW(), INTERVAL ' . LOGIN_BLOQUEO_MINUTOS . ' MINUTE), bloqueado_hasta)'
    )->execute([$clave]);
    $stmt = $pdo->prepare('SELECT intentos FROM intentos_login WHERE usuario = ?');
    $stmt->execute([$clave]);
    return max(LOGIN_MAX_INTENTOS - (int) $stmt->fetchColumn(), 0);
}

function limpiarIntentos(PDO $pdo, string $clave): void
{
    $pdo->prepare('DELETE FROM intentos_login WHERE usuario = ?')->execute([$clave]);
}
