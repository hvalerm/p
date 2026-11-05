<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$accesos = [];
$error = '';

// Solo accesos de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getPDO()->prepare(
        'SELECT a.id_acceso, a.activo_desde, a.activo_hasta, l.nombre_local, e.nombre_empresa,
                a.token, a.ejecutado, a.habilitado,
                (NOW() BETWEEN a.activo_desde AND a.activo_hasta) AS en_rango
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         INNER JOIN empresas e ON e.id_empresa = a.id_empresa
         INNER JOIN cuentas c ON c.id_empresa = a.id_empresa
         WHERE c.id_cuenta = ?
         ORDER BY a.id_acceso DESC'
    );
    $stmt->execute([$_SESSION['usuario_id']]);
    $accesos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en listarAccesos: ' . $e->getMessage());
    $error = 'No se pudieron cargar los accesos.';
}

$fmt = fn($f) => $f ? date('d/m/Y H:i', strtotime($f)) : '';
$si = fn($v) => $v ? 'Sí' : 'No';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Revocar accesos | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 8px; }
        .filtros label { display: block; font-size: .8rem; color: #6b7a86; margin-bottom: 4px; }
        .filtros input, .filtros select { width: 100%; padding: 9px 10px; border: 1px solid #cfd8de; border-radius: 8px; font: inherit; background: #fff; }
        .filtros input:focus, .filtros select:focus { outline: 2px solid #3a8fb7; border-color: transparent; }
        .contador { font-size: .85rem; color: #6b7a86; margin: 10px 0 14px; }
        .tabla { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e1e8ec; white-space: nowrap; }
        th { color: #6b7a86; font-weight: 600; background: #f7f9fa; }
        td.token { max-width: 200px; overflow: hidden; text-overflow: ellipsis; font-family: Consolas, monospace; font-size: .8rem; }
        .btn-fila { display: inline-block; padding: 5px 12px; border-radius: 6px; color: #fff; text-decoration: none; font-weight: 600; font-size: .85rem; }
        .btn-fila.revocar { background: #b3261e; }
        .btn-fila.conceder { background: #25a244; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        .si { color: #1e8e3e; font-weight: 700; }
        .no { color: #c0392b; font-weight: 700; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Revocar accesos</h1>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$accesos): ?>
            <p class="vacio">Tu empresa no tiene accesos registrados.</p>
        <?php else: ?>
            <div class="filtros">
                <div><label for="f-id">ID acceso</label><input type="search" id="f-id" data-col="0" autocomplete="off"></div>
                <div><label for="f-desde">Activo desde (a partir de)</label><input type="date" id="f-desde"></div>
                <div><label for="f-hasta">Activo hasta (hasta)</label><input type="date" id="f-hasta"></div>
                <div><label for="f-local">Local</label><input type="search" id="f-local" data-col="3" autocomplete="off"></div>
                <div><label for="f-empresa">Empresa</label><input type="search" id="f-empresa" data-col="4" autocomplete="off"></div>
                <div><label for="f-token">Token</label><input type="search" id="f-token" data-col="5" autocomplete="off"></div>
                <div><label for="f-ejecutado">Ejecutado</label>
                    <select id="f-ejecutado" data-col="6" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
                <div><label for="f-habilitado">Habilitado</label>
                    <select id="f-habilitado" data-col="7" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
                <div><label for="f-enrango">¿Se puede usar?</label>
                    <select id="f-enrango" data-col="8" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
            </div>
            <p class="contador" id="contador" aria-live="polite"></p>
            <div class="tabla">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Activo desde</th><th>Activo hasta</th><th>Local</th><th>Empresa</th><th>Token</th><th>Ejecutado</th><th>Habilitado</th><th>¿Se puede usar?</th><th>Acción</th></tr>
                    </thead>
                    <tbody id="cuerpo">
                        <?php foreach ($accesos as $a): ?>
                            <tr data-desde="<?= $h(date('Y-m-d', strtotime($a['activo_desde']))) ?>" data-hasta="<?= $h(date('Y-m-d', strtotime($a['activo_hasta']))) ?>">
                                <td><?= (int) $a['id_acceso'] ?></td>
                                <td><?= $h($fmt($a['activo_desde'])) ?></td>
                                <td><?= $h($fmt($a['activo_hasta'])) ?></td>
                                <td><?= $h($a['nombre_local']) ?></td>
                                <td><?= $h($a['nombre_empresa']) ?></td>
                                <td class="token" title="<?= $h($a['token']) ?>"><?= $h($a['token']) ?></td>
                                <td><?= $si($a['ejecutado']) ?></td>
                                <td><?= $si($a['habilitado']) ?></td>
                                <td class="<?= $a['en_rango'] ? 'si' : 'no' ?>"><?= $si($a['en_rango']) ?></td>
                                <td><a class="btn-fila <?= $a['habilitado'] ? 'revocar' : 'conceder' ?>" href="revocarAcceso.php?id=<?= (int) $a['id_acceso'] ?>&amp;origen=busqueda<?= $embed ? '&amp;embed=1' : '' ?>"><?= $a['habilitado'] ? 'Revocar' : 'Conceder' ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="vacio" id="sinResultados" hidden>Ningún acceso coincide con los filtros.</p>
        <?php endif; ?>
    </section>

    <?php if ($accesos): ?>
    <script>
        (function () {
            var filas = Array.prototype.slice.call(document.querySelectorAll('#cuerpo tr'));
            var campos = Array.prototype.slice.call(document.querySelectorAll('.filtros [data-col]'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');

            var fDesde = document.getElementById('f-desde');
            var fHasta = document.getElementById('f-hasta');

            // Minúsculas y sin acentos para que "si" y "Sí" coincidan
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            var datos = filas.map(function (tr) {
                return {
                    tr: tr,
                    desde: tr.dataset.desde,
                    hasta: tr.dataset.hasta,
                    celdas: Array.prototype.map.call(tr.cells, function (td) { return norm(td.textContent); })
                };
            });

            function filtrar() {
                var activos = campos.map(function (c) {
                    return { col: +c.dataset.col, v: norm(c.value), exacto: !!c.dataset.exacto };
                }).filter(function (f) { return f.v !== ''; });
                var visibles = 0;
                datos.forEach(function (x) {
                    // El formato yyyy-mm-dd permite comparar como texto
                    var ok = activos.every(function (f) {
                        return f.exacto ? x.celdas[f.col] === f.v : x.celdas[f.col].indexOf(f.v) !== -1;
                    }) && (!fDesde.value || x.desde >= fDesde.value)
                       && (!fHasta.value || x.hasta <= fHasta.value);
                    x.tr.hidden = !ok;
                    if (ok) visibles++;
                });
                sinResultados.hidden = visibles !== 0;
                contador.textContent = visibles + ' de ' + datos.length + ' accesos';
            }

            campos.forEach(function (c) {
                c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrar);
            });
            fDesde.addEventListener('input', filtrar);
            fHasta.addEventListener('input', filtrar);
            filtrar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
