<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';

$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

// Estado actual de accionar solo si el local pertenece a la empresa de la cuenta
function estadoAccionar(int $idLocal, int $idCuenta): ?int
{
    $stmt = getPDO()->prepare(
        'SELECT l.accionar
         FROM locales l
         INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
         WHERE l.id_local = ? AND c.id_cuenta = ? AND l.eliminado = 0'
    );
    $stmt->execute([$idLocal, $idCuenta]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

// Consulta del subproceso JS: devuelve JSON con el valor de accionar
if (isset($_GET['estado'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!haySesionActiva()) {
        http_response_code(401);
        echo json_encode(['error' => 'sesion']);
        exit;
    }
    try {
        $accionar = $idLocal ? estadoAccionar($idLocal, (int) $_SESSION['usuario_id']) : null;
    } catch (PDOException $e) {
        error_log('Error en puertaAccionada: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'bd']);
        exit;
    }
    if ($accionar === null) {
        http_response_code(404);
        echo json_encode(['error' => 'local']);
        exit;
    }
    echo json_encode(['accionar' => $accionar]);
    exit;
}

requerirSesion();
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$existe = false;
try {
    $existe = $idLocal && estadoAccionar($idLocal, (int) $_SESSION['usuario_id']) !== null;
} catch (PDOException $e) {
    error_log('Error en puertaAccionada: ' . $e->getMessage());
}
$urlEstado = 'puertaAccionada.php?estado=1&id=' . (int) $idLocal;
$urlVolver = 'localSeleccionado.php?id=' . (int) $idLocal . ($embed ? '&embed=1' : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apertura de puerta | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); text-align: center; }
        .estado { display: flex; align-items: center; justify-content: center; gap: 14px; font-size: 1.3rem; font-weight: 600; }
        .estado .cara { font-size: 2.2rem; line-height: 1; }
        .estado.espera { color: #6b7a86; }
        .estado.ok { color: #1e8e3e; }
        .estado.fallo { color: #c5221f; }
        .spinner { width: 22px; height: 22px; border: 3px solid #d5dde2; border-top-color: #2c5364; border-radius: 50%; animation: giro 1s linear infinite; }
        @keyframes giro { to { transform: rotate(360deg); } }
        .volver { display: inline-block; margin-top: 26px; color: #2c5364; font-weight: 600; text-decoration: none; }
        .volver:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$existe): ?>
            <p>El local no existe o no pertenece a tu empresa.</p>
        <?php else: ?>
            <div class="estado espera" id="estado" role="status" aria-live="polite">
                <span class="spinner" id="icono"></span>
                <span id="texto">Orden de apertura de puerta emitida, esperando confirmación</span>
            </div>
            <a class="volver" href="<?= $h($urlVolver) ?>">Volver al local</a>
        <?php endif; ?>
    </section>

    <?php if ($existe): ?>
    <script>
        (function () {
            var ESPERA_MAX_MS = 60000;
            var INTERVALO_MS = 1500;
            var estado = document.getElementById('estado');
            var icono = document.getElementById('icono');
            var texto = document.getElementById('texto');
            var url = new URL(<?= json_encode($urlEstado) ?>, location.href).href;

            function mostrar(clase, cara, mensaje) {
                estado.className = 'estado ' + clase;
                icono.className = 'cara';
                icono.textContent = cara;
                texto.textContent = mensaje;
            }

            // Subproceso (Web Worker) que consulta el valor de accionar sin bloquear la página
            var codigo =
                'var url, intervalo, limite, timer;' +
                'function consultar() {' +
                '  fetch(url, { credentials: "include", cache: "no-store" })' +
                '    .then(function (r) { return r.ok ? r.json() : { error: r.status }; })' +
                '    .then(function (d) { if (d.accionar === 0) { fin("ok"); } })' +
                '    .catch(function () {});' +
                '}' +
                'function fin(r) { clearInterval(timer); clearTimeout(limite); postMessage(r); close(); }' +
                'onmessage = function (e) {' +
                '  url = e.data.url;' +
                '  timer = setInterval(consultar, e.data.intervalo);' +
                '  limite = setTimeout(function () { fin("fallo"); }, e.data.limite);' +
                '  consultar();' +
                '};';

            var worker = new Worker(URL.createObjectURL(new Blob([codigo], { type: 'text/javascript' })));
            worker.onmessage = function (e) {
                if (e.data === 'ok') {
                    mostrar('ok', '😊', 'La orden fue ejecutada con éxito');
                } else {
                    mostrar('fallo', '😞', 'Ejecución de la orden de apertura de puerta falló');
                }
                worker.terminate();
            };
            worker.postMessage({ url: url, intervalo: INTERVALO_MS, limite: ESPERA_MAX_MS });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
