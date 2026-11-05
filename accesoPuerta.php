<?php
require_once __DIR__ . '/util/db.php';

header('Cache-Control: no-store');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$token = $_GET['token'] ?? '';
$estado = 'invalido';
$acceso = null;

if (is_string($token) && preg_match('/^[A-Za-z0-9]{1,250}$/', $token)) {
    try {
        $stmt = getPDO()->prepare(
            'SELECT a.activo_desde, a.activo_hasta, a.ejecutado, a.habilitado,
                    l.nombre_local, l.direccion_local
             FROM accesos a
             INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
             WHERE a.token = ? AND l.eliminado = 0'
        );
        $stmt->execute([$token]);
        $acceso = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error en accesoPuerta: ' . $e->getMessage());
        $estado = 'error';
    }

    if ($estado !== 'error') {
        $ahora = date('Y-m-d H:i:s');
        if (!$acceso) {
            $estado = 'invalido';
        } elseif ($acceso['ejecutado']) {
            $estado = 'usado';
        } elseif (!$acceso['habilitado']) {
            $estado = 'deshabilitado';
        } elseif ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
            $estado = 'fuera_de_horario';
        } else {
            $estado = 'valido';
        }
    }
}

$mensajes = [
    'usado' => 'Acceso usado.',
    'invalido' => 'El enlace de acceso no es válido.',
    'deshabilitado' => 'Este acceso está deshabilitado.',
    'fuera_de_horario' => 'Este acceso no está vigente en este momento.',
    'error' => 'Ocurrió un error. Inténtalo nuevamente en unos minutos.',
];
$urlApertura = 'ApiSystem/ordenDeApertura_porLinkAcceso.php?token=' . rawurlencode((string) $token);
$fmt = fn($f) => (new DateTime($f))->format('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | OpenDoors</title>
    <link rel="icon" type="image/png" href="img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); padding: 20px; }
        .card { width: 100%; max-width: 480px; background: #fff; border-radius: 14px; padding: 32px 28px; box-shadow: 0 10px 30px rgba(0,0,0,.3); text-align: center; }
        h1 { font-size: 1.4rem; color: #1b2a35; margin-bottom: 18px; }
        .dato { text-align: left; background: #f4f7f9; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; }
        .dato span { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7a86; }
        .dato strong { color: #1b2a35; font-size: 1.05rem; }
        .advertencia { margin: 20px 0 10px; padding: 16px; border: 3px solid #c0392b; border-radius: 10px; background: #fdecea; color: #8e1b10; text-align: left; font-weight: 700; line-height: 1.45; }
        .advertencia .titulo { display: block; font-size: 1.1rem; margin-bottom: 6px; text-transform: uppercase; }
        .unico { margin-bottom: 18px; padding: 12px 14px; border: 2px solid #e67e22; border-radius: 10px; background: #fff4e5; color: #8a4b08; text-align: left; font-weight: 600; line-height: 1.4; }
        .btn { display: block; width: 100%; padding: 15px; border-radius: 8px; background: #27ae60; color: #fff; font-size: 1.1rem; font-weight: 700; text-decoration: none; }
        .btn:hover { background: #1f8f4f; }
        .estado { padding: 22px 16px; border-radius: 10px; background: #fdecea; color: #8e1b10; font-size: 1.3rem; font-weight: 700; }
    </style>
</head>
<body>
    <section class="card">
        <?php if ($estado === 'valido'): ?>
            <h1>Acceso al alojamiento</h1>
            <div class="dato"><span>Local</span><strong><?= $h($acceso['nombre_local']) ?></strong></div>
            <div class="dato"><span>Dirección</span><strong><?= $h($acceso['direccion_local']) ?></strong></div>
            <div class="dato"><span>Vigente hasta</span><strong><?= $h($fmt($acceso['activo_hasta'])) ?></strong></div>

            <div class="advertencia" role="alert">
                <span class="titulo">⚠ Advertencia de seguridad</span>
                Debes estar físicamente frente a la puerta del hospedaje antes de continuar. Al presionar el botón la puerta se abrirá y solo deben ingresar los huéspedes.
            </div>
            <div class="unico">La apertura es de un único uso: una vez ejecutada, este enlace no podrá volver a utilizarse.</div>

            <a class="btn" href="<?= $h($urlApertura) ?>">Abrir puerta</a>
        <?php else: ?>
            <h1>Acceso al alojamiento</h1>
            <div class="estado"><?= $h($mensajes[$estado]) ?></div>
        <?php endif; ?>
    </section>
</body>
</html>
