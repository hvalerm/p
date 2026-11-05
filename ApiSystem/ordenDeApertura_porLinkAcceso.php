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

// Paso 1: recibir el token por POST (un GET lo podría consumir una vista previa del enlace) y validar su formato
// (solo letras y números, hasta 250 caracteres)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, false, 'Método no permitido.');
}
$token = $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{1,250}$/', $token)) {
    responder(400, false, 'Token no válido.');
}

try {
    $pdo = getPDO();
    // Paso 2: comparar el token con accesos.token y obtener los datos del acceso y su local
    // (el acceso no debe estar ejecutado y el local no debe estar eliminado)
    $stmt = $pdo->prepare(
        'SELECT a.id_acceso, a.id_local, a.id_empresa, a.activo_desde, a.activo_hasta, a.habilitado, l.accionar
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         WHERE a.token = ? AND a.ejecutado = 0 AND l.eliminado = 0'
    );
    $stmt->execute([$token]);
    $acceso = $stmt->fetch();

    if (!$acceso) {
        responder(404, false, 'Acceso no encontrado.');
    }
    // Paso 3: verificar que el acceso esté habilitado
    if (!$acceso['habilitado']) {
        responder(403, false, 'El acceso está deshabilitado.');
    }
    // Paso 4: verificar que la hora actual esté dentro del rango de vigencia
    $ahora = date('Y-m-d H:i:s');
    if ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
        responder(403, false, 'El acceso está fuera de su horario de vigencia.');
    }

    // Paso 5: emitir la orden de apertura (accionar = 1 en el local) y marcar el acceso como ejecutado
    $pdo->beginTransaction();

    $marca = $pdo->prepare('UPDATE accesos SET ejecutado = 1 WHERE id_acceso = ? AND ejecutado = 0');
    $marca->execute([$acceso['id_acceso']]);
    if ($marca->rowCount() !== 1) {
        $pdo->rollBack();
        responder(404, false, 'Acceso no encontrado.');
    }

    $upd = $pdo->prepare('UPDATE locales SET accionar = 1 WHERE id_local = ? AND id_empresa = ?');
    $upd->execute([$acceso['id_local'], $acceso['id_empresa']]);

    $pdo->commit();

    // Paso 6: responder que la orden fue emitida
    responder(200, true, 'Orden de apertura emitida.');
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en ApiSystem/ordenDeApertura_porLinkAcceso: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
}
