<?php
require_once __DIR__ . '/../util/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, bool $ok, string $mensaje): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{1,250}$/', $token)) {
    responder(400, false, 'Token no válido.');
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT a.id_acceso, a.id_local, a.id_empresa, a.activo_desde, a.activo_hasta, a.habilitado
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         WHERE a.token = ? AND l.eliminado = 0'
    );
    $stmt->execute([$token]);
    $acceso = $stmt->fetch();

    if (!$acceso) {
        responder(404, false, 'Acceso no encontrado.');
    }
    if (!$acceso['habilitado']) {
        responder(403, false, 'El acceso está deshabilitado.');
    }
    $ahora = date('Y-m-d H:i:s');
    if ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
        responder(403, false, 'El acceso está fuera de su horario de vigencia.');
    }

    $upd = $pdo->prepare('UPDATE locales SET accionar = 1 WHERE id_local = ? AND id_empresa = ?');
    $upd->execute([$acceso['id_local'], $acceso['id_empresa']]);

    responder(200, true, 'Orden de apertura emitida.');
} catch (PDOException $e) {
    error_log('Error en ApiSystem/acceso: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
}
