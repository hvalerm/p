<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idLocal = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']) || isset($_POST['embed']);
$local = null;
$error = '';
$creado = null;
$desde = trim($_POST['activo_desde'] ?? '');
$hasta = trim($_POST['activo_hasta'] ?? '');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function generarToken(int $largo = 250): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    $t = '';
    for ($i = 0; $i < $largo; $i++) {
        $t .= $chars[random_int(0, $max)];
    }
    return $t;
}

function parsearFecha(string $v): ?DateTime
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $v);
    return $d ?: null;
}

try {
    $pdo = getPDO();
    if ($idLocal) {
        $stmt = $pdo->prepare(
            'SELECT l.id_local, l.id_empresa, l.nombre_local, l.direccion_local, e.nombre_empresa, l.habilitado
             FROM locales l
             INNER JOIN empresas e ON e.id_empresa = l.id_empresa
             INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
             WHERE l.id_local = ? AND c.id_cuenta = ? AND l.eliminado = 0'
        );
        $stmt->execute([$idLocal, $_SESSION['usuario_id']]);
        $local = $stmt->fetch() ?: null;
    }

    if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d1 = parsearFecha($desde);
        $d2 = parsearFecha($hasta);
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
        } elseif (!$d1 || !$d2) {
            $error = 'Ingresa fechas y horas válidas.';
        } elseif ($d2 <= $d1) {
            $error = 'La fecha "Activo hasta" debe ser posterior a "Activo desde".';
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO accesos (activo_desde, activo_hasta, id_local, id_empresa, token) VALUES (?, ?, ?, ?, ?)'
            );
            for ($intento = 0; $intento < 5 && !$creado; $intento++) {
                $token = generarToken();
                try {
                    $ins->execute([
                        $d1->format('Y-m-d H:i:s'), $d2->format('Y-m-d H:i:s'),
                        $local['id_local'], $local['id_empresa'], $token,
                    ]);
                    $creado = ['id' => $pdo->lastInsertId(), 'token' => $token];
                } catch (PDOException $e) {
                    // 1062 = token duplicado: se reintenta con otro token
                    if (($e->errorInfo[1] ?? 0) != 1062) {
                        throw $e;
                    }
                }
            }
            if (!$creado) {
                $error = 'No se pudo generar un token único. Inténtalo de nuevo.';
            }
        }
    }
} catch (PDOException $e) {
    error_log('Error en CrearAccesoLocal: ' . $e->getMessage());
    $error = 'Ocurrió un error al guardar el acceso.';
}
$q = $embed ? '?embed=1' : '';
$urlVolver = 'localSeleccionado.php?id=' . (int) $idLocal . ($embed ? '&embed=1' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear acceso | Administrar Alojamiento</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        h2 { font-size: 1.1rem; margin: 22px 0 12px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 8px; }
        dt { color: #6b7a86; }
        label { display: block; margin-bottom: 14px; color: #6b7a86; }
        input[type=datetime-local] { display: block; width: 100%; margin-top: 6px; padding: 10px; border: 1px solid #c9d3da; border-radius: 8px; font: inherit; color: #1b2a35; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
        .btn { display: inline-block; border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #3a8fb7; cursor: pointer; text-decoration: none; }
        .btn:hover { background: #2f7897; }
        .btn.enlace { background: #6b7a86; }
        .error { background: #fdecea; color: #b3261e; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .ok { background: #e6f4ea; color: #1e7e34; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .linkfila { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 10px; }
        .linkfila .token { flex: 1 1 260px; margin-top: 0; }
        .btn.wsp { background: #25a244; }
        .token { word-break: break-all; font-family: Consolas, monospace; font-size: .85rem; background: #f2f5f7; padding: 10px; border-radius: 8px; margin-top: 8px; color: #1b2a35; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$local): ?>
            <h1>Local no encontrado</h1>
            <p>El local no existe o no pertenece a tu empresa.</p>
            <div class="acciones" style="margin-top:18px"><a class="btn enlace" href="SeleccionarLocalAcceso.php<?= $h($q) ?>">Volver a la lista</a></div>
        <?php else: ?>
            <h1>Crear acceso</h1>
            <dl>
                <dt>Local</dt><dd><?= $h($local['nombre_local']) ?></dd>
                <dt>Dirección</dt><dd><?= $h($local['direccion_local']) ?></dd>
                <dt>Empresa</dt><dd><?= $h($local['nombre_empresa']) ?></dd>
                <dt>Habilitado</dt><dd><?= $local['habilitado'] ? 'Sí' : 'No' ?></dd>
            </dl>

            <?php if ($creado): ?>
                <div class="ok">
                    Acceso creado correctamente.
                    <div class="token"><?= $h($creado['token']) ?></div>
                    <?php $link = 'https://opendoors.onrender.com/accesoPuerta.php?token=' . $creado['token']; ?>
                    <div class="linkfila">
                        <div class="token" id="linkAcceso"><?= $h($link) ?></div>
                        <button type="button" class="btn" id="btnCopiar">Copiar link</button>
                        <a class="btn wsp" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($link) ?>">Compartir por WhatsApp</a>
                    </div>
                </div>
                <div class="acciones">
                    <a class="btn" href="CrearAccesoLocal.php?id=<?= (int) $local['id_local'] ?><?= $embed ? '&amp;embed=1' : '' ?>">Crear otro acceso</a>
                    <a class="btn enlace" href="<?= $h($urlVolver) ?>">Volver al local</a>
                </div>
            <?php else: ?>
                <h2>Datos del acceso</h2>
                <?php if ($error): ?><div class="error"><?= $h($error) ?></div><?php endif; ?>
                <form method="post" action="CrearAccesoLocal.php">
                    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                    <input type="hidden" name="id" value="<?= (int) $local['id_local'] ?>">
                    <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                    <label>Activo desde
                        <input type="datetime-local" name="activo_desde" required value="<?= $h($desde) ?>">
                    </label>
                    <label>Activo hasta
                        <input type="datetime-local" name="activo_hasta" required value="<?= $h($hasta) ?>">
                    </label>
                    <div class="acciones">
                        <button type="submit" class="btn">Crear acceso</button>
                        <a class="btn enlace" href="<?= $h($urlVolver) ?>">Cancelar</a>
                    </div>
                </form>
            <?php endif; ?>
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
