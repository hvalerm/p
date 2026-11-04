<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idAcceso = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']) || isset($_POST['embed']);
$acceso = null;
$error = '';
$mensaje = '';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Solo accesos de la empresa de la cuenta con sesión iniciada
function cargarAcceso(PDO $pdo, int $idAcceso, int $idCuenta): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.id_acceso, a.id_empresa, a.activo_desde, a.activo_hasta, l.nombre_local, e.nombre_empresa,
                a.token, a.ejecutado, a.habilitado
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         INNER JOIN empresas e ON e.id_empresa = a.id_empresa
         INNER JOIN cuentas c ON c.id_empresa = a.id_empresa
         WHERE a.id_acceso = ? AND c.id_cuenta = ?'
    );
    $stmt->execute([$idAcceso, $idCuenta]);
    return $stmt->fetch() ?: null;
}

function parsearFecha(string $v): ?DateTime
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $v);
    return $d ?: null;
}

$desde = null;
$hasta = null;
try {
    $pdo = getPDO();
    $idCuenta = (int) $_SESSION['usuario_id'];
    if ($idAcceso) {
        $acceso = cargarAcceso($pdo, $idAcceso, $idCuenta);
    }

    if ($acceso && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
        } elseif ($accion === 'toggle') {
            $nuevo = $acceso['habilitado'] ? 0 : 1;
            $pdo->prepare('UPDATE accesos SET habilitado = ? WHERE id_acceso = ? AND id_empresa = ?')
                ->execute([$nuevo, $acceso['id_acceso'], $acceso['id_empresa']]);
            $mensaje = $nuevo ? 'El acceso fue concedido nuevamente.' : 'El acceso fue revocado.';
        } elseif ($accion === 'actualizar') {
            $desde = trim($_POST['activo_desde'] ?? '');
            $hasta = trim($_POST['activo_hasta'] ?? '');
            $d1 = parsearFecha($desde);
            $d2 = parsearFecha($hasta);
            $idLocalNuevo = filter_var($_POST['id_local'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $ejecutadoNuevo = ($_POST['ejecutado'] ?? '') === '1' ? 1 : 0;
            $habilitadoNuevo = ($_POST['habilitado'] ?? '') === '1' ? 1 : 0;
            $chk = $pdo->prepare('SELECT 1 FROM locales WHERE id_local = ? AND id_empresa = ? AND eliminado = 0');
            $chk->execute([$idLocalNuevo ?: 0, $acceso['id_empresa']]);
            if (!$d1 || !$d2) {
                $error = 'Ingresa fechas y horas válidas.';
            } elseif ($d2 <= $d1) {
                $error = 'La fecha "Activo hasta" debe ser posterior a "Activo desde".';
            } elseif (!$chk->fetchColumn()) {
                $error = 'El local seleccionado no es válido.';
            } else {
                $pdo->prepare(
                    'UPDATE accesos SET activo_desde = ?, activo_hasta = ?, id_local = ?, ejecutado = ?, habilitado = ?
                     WHERE id_acceso = ? AND id_empresa = ?'
                )->execute([
                    $d1->format('Y-m-d H:i:s'), $d2->format('Y-m-d H:i:s'), $idLocalNuevo,
                    $ejecutadoNuevo, $habilitadoNuevo, $acceso['id_acceso'], $acceso['id_empresa'],
                ]);
                $mensaje = 'El acceso fue actualizado.';
                $desde = $hasta = null;
            }
        } else {
            $error = 'Acción no válida.';
        }
        if (!$error) {
            $acceso = cargarAcceso($pdo, $idAcceso, $idCuenta);
        }
    }
} catch (PDOException $e) {
    error_log('Error en vetanaDelAcceso: ' . $e->getMessage());
    $error = 'Ocurrió un error al actualizar el acceso.';
}

$urlLista = 'listarAccesos.php' . ($embed ? '?embed=1' : '');
$locales = [];
if ($acceso) {
    try {
        $st = getPDO()->prepare('SELECT id_local, nombre_local FROM locales WHERE id_empresa = ? AND eliminado = 0 ORDER BY nombre_local');
        $st->execute([$acceso['id_empresa']]);
        $locales = $st->fetchAll();
    } catch (PDOException $e) {
        error_log('Error en vetanaDelAcceso (locales): ' . $e->getMessage());
    }
}
$link = $acceso ? 'https://opendoors.onrender.com/accesoPuerta.php?token=' . $acceso['token'] : '';
$valDesde = $desde ?? ($acceso ? date('Y-m-d\TH:i', strtotime($acceso['activo_desde'])) : '');
$valHasta = $hasta ?? ($acceso ? date('Y-m-d\TH:i', strtotime($acceso['activo_hasta'])) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 20px; align-items: center; }
        dt { color: #6b7a86; }
        dd { word-break: break-all; }
        dd input, dd select { width: 100%; padding: 9px 10px; border: 1px solid #c9d3da; border-radius: 8px; font: inherit; color: #1b2a35; background: #fff; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .btn { display: inline-block; border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #3a8fb7; cursor: pointer; text-decoration: none; }
        .btn.peligro { background: #b3261e; }
        .btn.ok { background: #25a244; }
        .btn.wsp { background: #25a244; }
        .btn.enlace { background: #6b7a86; }
        .error { background: #fdecea; color: #b3261e; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .exito { background: #e6f4ea; color: #1e7e34; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .link { word-break: break-all; font-family: Consolas, monospace; font-size: .85rem; background: #f2f5f7; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$acceso): ?>
            <h1>Acceso no encontrado</h1>
            <p>El acceso no existe o no pertenece a tu empresa.</p>
            <div class="acciones" style="margin-top:18px"><a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a></div>
        <?php else: ?>
            <h1>Acceso <?= (int) $acceso['id_acceso'] ?></h1>
            <?php if ($error): ?><div class="error"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($mensaje): ?><div class="exito"><?= $h($mensaje) ?></div><?php endif; ?>

            <form method="post" action="vetanaDelAcceso.php">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <input type="hidden" name="id" value="<?= (int) $acceso['id_acceso'] ?>">
                <input type="hidden" name="accion" value="actualizar">
                <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <dl>
                    <dt>Local</dt>
                    <dd>
                        <select name="id_local">
                            <?php foreach ($locales as $l): ?>
                                <option value="<?= (int) $l['id_local'] ?>" <?= $l['nombre_local'] === $acceso['nombre_local'] ? 'selected' : '' ?>><?= $h($l['nombre_local']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </dd>
                    <dt>Empresa</dt><dd><?= $h($acceso['nombre_empresa']) ?></dd>
                    <dt>Activo desde</dt><dd><input type="datetime-local" name="activo_desde" required value="<?= $h($valDesde) ?>"></dd>
                    <dt>Activo hasta</dt><dd><input type="datetime-local" name="activo_hasta" required value="<?= $h($valHasta) ?>"></dd>
                    <dt>Token</dt><dd><?= $h($acceso['token']) ?></dd>
                    <dt>Ejecutado</dt>
                    <dd><select name="ejecutado"><option value="1" <?= $acceso['ejecutado'] ? 'selected' : '' ?>>Sí</option><option value="0" <?= $acceso['ejecutado'] ? '' : 'selected' ?>>No</option></select></dd>
                    <dt>Habilitado</dt>
                    <dd><select name="habilitado"><option value="1" <?= $acceso['habilitado'] ? 'selected' : '' ?>>Sí</option><option value="0" <?= $acceso['habilitado'] ? '' : 'selected' ?>>No</option></select></dd>
                </dl>
                <div class="acciones">
                    <button type="submit" class="btn">Actualizar</button>
                    <button type="submit" class="btn <?= $acceso['habilitado'] ? 'peligro' : 'ok' ?>" name="accion" value="toggle" formnovalidate>
                        <?= $acceso['habilitado'] ? 'Revocar acceso' : 'Conceder acceso' ?>
                    </button>
                </div>
            </form>

            <div class="link" id="linkAcceso"><?= $h($link) ?></div>
            <div class="acciones">
                <button type="button" class="btn" id="btnCopiar">Copiar link</button>
                <a class="btn wsp" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($link) ?>">Compartir por WhatsApp</a>
                <a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a>
            </div>
        <?php endif; ?>
    </section>
    <script>
        const btnCopiar = document.getElementById('btnCopiar');
        if (btnCopiar) {
            btnCopiar.addEventListener('click', async () => {
                const texto = document.getElementById('linkAcceso').textContent.trim();
                try {
                    await navigator.clipboard.writeText(texto);
                } catch (e) {
                    const ta = document.createElement('textarea');
                    ta.value = texto;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                btnCopiar.textContent = '¡Copiado!';
                setTimeout(() => { btnCopiar.textContent = 'Copiar link'; }, 2000);
            });
        }
    </script>
</body>
</html>
