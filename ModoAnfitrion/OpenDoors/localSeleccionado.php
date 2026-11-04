<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$usuario = usuarioActual();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']);
$mensaje = '';
$esError = false;
$local = null;

// Solo se accede a locales de la empresa de la cuenta con sesión iniciada
function cargarLocal(int $idLocal, int $idCuenta): ?array
{
    $stmt = getPDO()->prepare(
        'SELECT l.id_local, l.nombre_local, l.direccion_local, e.nombre_empresa,
                l.accionar, l.habilitado, l.eliminado, l.id_empresa
         FROM locales l
         INNER JOIN empresas e ON e.id_empresa = l.id_empresa
         INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
         WHERE l.id_local = ? AND c.id_cuenta = ? AND l.eliminado = 0'
    );
    $stmt->execute([$idLocal, $idCuenta]);
    return $stmt->fetch() ?: null;
}

try {
    if ($idLocal) {
        $local = cargarLocal($idLocal, (int) $usuario['id']);
    }

    if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            $mensaje = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
            $esError = true;
        } elseif (($_POST['accion'] ?? '') === 'abrir_puerta') {
            if (!$local['habilitado']) {
                $mensaje = 'El local está deshabilitado.';
                $esError = true;
            } else {
                $stmt = getPDO()->prepare('UPDATE locales SET accionar = 1 WHERE id_local = ? AND id_empresa = ?');
                $stmt->execute([$local['id_local'], $local['id_empresa']]);
                header('Location: puertaAccionada.php?id=' . $local['id_local'] . ($embed ? '&embed=1' : ''));
                exit;
            }
        } elseif (($_POST['accion'] ?? '') === 'anular_apertura') {
            $stmt = getPDO()->prepare('UPDATE locales SET accionar = 0 WHERE id_local = ? AND id_empresa = ?');
            $stmt->execute([$local['id_local'], $local['id_empresa']]);
            $mensaje = 'La apertura de puerta fue anulada.';
            $local = cargarLocal($idLocal, (int) $usuario['id']);
        }
    }
} catch (PDOException $e) {
    error_log('Error en localSeleccionado: ' . $e->getMessage());
    $mensaje = 'No se pudo completar la operación. Inténtalo más tarde.';
    $esError = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local seleccionado | Administrar Alojamiento</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 24px; }
        dt { color: #6b7a86; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; }
        .acciones form { margin: 0; }
        .btn { border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #2c5364; cursor: pointer; }
        .btn:hover { background: #203a43; }
        .btn.secundario { background: #3a8fb7; }
        .btn.peligro { background: #b3261e; }
        .btn.peligro:hover { background: #8c1d18; }
        .aviso { margin-bottom: 18px; padding: 10px 14px; border-radius: 8px; background: #e6f4ea; color: #1e6b34; }
        .aviso.error { background: #fdecea; color: #b3261e; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$local): ?>
            <h1>Local no encontrado</h1>
            <p>El local no existe o no pertenece a tu empresa.</p>
        <?php else: ?>
            <h1><?= $h($local['nombre_local']) ?></h1>
            <?php if ($mensaje): ?>
                <p class="aviso<?= $esError ? ' error' : '' ?>"><?= $h($mensaje) ?></p>
            <?php endif; ?>
            <dl>
                <dt>Nombre</dt><dd><?= $h($local['nombre_local']) ?></dd>
                <dt>Dirección</dt><dd><?= $h($local['direccion_local']) ?></dd>
                <dt>Empresa</dt><dd><?= $h($local['nombre_empresa']) ?></dd>
                <dt>Accionar</dt><dd><?= $local['accionar'] ? 'Activado (puerta solicitada)' : 'Inactivo' ?></dd>
                <dt>Habilitado</dt><dd><?= $local['habilitado'] ? 'Sí' : 'No' ?></dd>
            </dl>
            <div class="acciones">
                <form method="post" action="">
                    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="accion" value="abrir_puerta">
                    <button type="submit" class="btn">Abrir puerta</button>
                </form>
                <form method="post" action="">
                    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="accion" value="anular_apertura">
                    <button type="submit" class="btn peligro">Anular apertura de puerta</button>
                </form>
                <button type="button" class="btn secundario">Crear Acceso</button>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>
