<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$unidades = [];
$error = '';

// Solo unidades de control de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getPDO()->prepare(
        'SELECT m.id_microcontrolador, l.nombre_local, l.direccion_local, m.cantidadAperturas
         FROM microcontroladores m
         INNER JOIN locales l ON l.id_local = m.id_local AND l.id_empresa = m.id_empresa
         INNER JOIN cuentas c ON c.id_empresa = m.id_empresa
         WHERE c.id_cuenta = ?
         ORDER BY m.id_microcontrolador'
    );
    $stmt->execute([$_SESSION['usuario_id']]);
    $unidades = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en verActividad: ' . $e->getMessage());
    $error = 'No se pudo cargar la actividad de las unidades de control.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actividad de unidades de control | OpenDoors</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        .tabla { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e1e8ec; }
        th { color: #6b7a86; font-weight: 600; background: #f7f9fa; white-space: nowrap; }
        td.num, th.num { text-align: right; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Actividad de unidades de control</h1>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$unidades): ?>
            <p class="vacio">Tu empresa no tiene unidades de control registradas.</p>
        <?php else: ?>
            <div class="tabla">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Local</th><th>Dirección</th><th class="num">Veces ejecutado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unidades as $u): ?>
                            <tr>
                                <td><?= (int) $u['id_microcontrolador'] ?></td>
                                <td><?= $h($u['nombre_local']) ?></td>
                                <td><?= $h($u['direccion_local']) ?></td>
                                <td class="num"><?= (int) $u['cantidadAperturas'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>
