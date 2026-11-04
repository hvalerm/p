<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']);
$local = null;

// Solo se accede a locales de la empresa de la cuenta con sesión iniciada
try {
    if ($idLocal) {
        $stmt = getPDO()->prepare(
            'SELECT l.id_local, l.nombre_local, l.direccion_local, e.nombre_empresa, l.habilitado
             FROM locales l
             INNER JOIN empresas e ON e.id_empresa = l.id_empresa
             INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
             WHERE l.id_local = ? AND c.id_cuenta = ? AND l.eliminado = 0'
        );
        $stmt->execute([$idLocal, $_SESSION['usuario_id']]);
        $local = $stmt->fetch() ?: null;
    }
} catch (PDOException $e) {
    error_log('Error en Accesos/localSeleccionado: ' . $e->getMessage());
}
$urlLista = 'SeleccionarLocalAcceso.php' . ($embed ? '?embed=1' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local seleccionado | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 24px; }
        dt { color: #6b7a86; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-block; border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #3a8fb7; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #2f7897; }
        .btn.enlace { background: #6b7a86; }
        .btn.enlace:hover { background: #55636d; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$local): ?>
            <h1>Local no encontrado</h1>
            <p>El local no existe o no pertenece a tu empresa.</p>
            <div class="acciones" style="margin-top:18px"><a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a></div>
        <?php else: ?>
            <h1><?= $h($local['nombre_local']) ?></h1>
            <dl>
                <dt>Nombre</dt><dd><?= $h($local['nombre_local']) ?></dd>
                <dt>Dirección</dt><dd><?= $h($local['direccion_local']) ?></dd>
                <dt>Empresa</dt><dd><?= $h($local['nombre_empresa']) ?></dd>
                <dt>Habilitado</dt><dd><?= $local['habilitado'] ? 'Sí' : 'No' ?></dd>
            </dl>
            <div class="acciones">
                <a class="btn" href="CrearAccesoLocal.php?id=<?= (int) $local['id_local'] ?><?= $embed ? '&amp;embed=1' : '' ?>">Crear Acceso de este local</a>
                <a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>
