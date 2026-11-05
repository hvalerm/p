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
        'SELECT m.id_microcontrolador, l.nombre_local, e.nombre_empresa,
                m.ultimaConsulta, m.ultimaApertura, m.habilitado,
                TIMESTAMPDIFF(SECOND, m.ultimaConsulta, NOW()) AS segundos_sin_consulta
         FROM microcontroladores m
         INNER JOIN locales l ON l.id_local = m.id_local AND l.id_empresa = m.id_empresa
         INNER JOIN empresas e ON e.id_empresa = m.id_empresa
         INNER JOIN cuentas c ON c.id_empresa = m.id_empresa
         WHERE c.id_cuenta = ?
         ORDER BY m.id_microcontrolador'
    );
    $stmt->execute([$_SESSION['usuario_id']]);
    $unidades = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en verEstado: ' . $e->getMessage());
    $error = 'No se pudieron cargar las unidades de control.';
}

$fmt = fn($f) => $f ? date('d/m/Y H:i:s', strtotime($f)) : '';
$si = fn($v) => $v ? 'Sí' : 'No';
// Activo si la última consulta fue hace 15 segundos o menos (según la hora de la BD)
$activo = fn($seg) => $seg !== null && (int) $seg <= 15;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unidades de control | OpenDoors</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
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
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        .estado-activo { color: #1e8e3e; font-weight: 700; }
        .estado-inactivo { color: #c0392b; font-weight: 700; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Unidades de control</h1>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$unidades): ?>
            <p class="vacio">Tu empresa no tiene unidades de control registradas.</p>
        <?php else: ?>
            <div class="filtros">
                <div><label for="f-id">ID</label><input type="search" id="f-id" data-col="0" autocomplete="off"></div>
                <div><label for="f-local">Local</label><input type="search" id="f-local" data-col="1" autocomplete="off"></div>
                <div><label for="f-empresa">Empresa</label><input type="search" id="f-empresa" data-col="2" autocomplete="off"></div>
                <div><label for="f-estado">Estado</label>
                    <select id="f-estado" data-col="5" data-exacto="1"><option value="">Todos</option><option>Activo</option><option>Inactivo</option></select></div>
                <div><label for="f-habilitado">Habilitado</label>
                    <select id="f-habilitado" data-col="6" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
            </div>
            <p class="contador" id="contador" aria-live="polite"></p>
            <div class="tabla">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Local</th><th>Empresa</th><th>Última consulta</th><th>Última apertura</th><th>Estado</th><th>Habilitado</th></tr>
                    </thead>
                    <tbody id="cuerpo">
                        <?php foreach ($unidades as $u): ?>
                            <tr>
                                <td><?= (int) $u['id_microcontrolador'] ?></td>
                                <td><?= $h($u['nombre_local']) ?></td>
                                <td><?= $h($u['nombre_empresa']) ?></td>
                                <td><?= $h($fmt($u['ultimaConsulta'])) ?></td>
                                <td><?= $h($fmt($u['ultimaApertura'])) ?></td>
                                <?php if ($activo($u['segundos_sin_consulta'])): ?>
                                    <td class="estado-activo">Activo</td>
                                <?php else: ?>
                                    <td class="estado-inactivo">Inactivo</td>
                                <?php endif; ?>
                                <td><?= $si($u['habilitado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="vacio" id="sinResultados" hidden>Ninguna unidad de control coincide con los filtros.</p>
        <?php endif; ?>
    </section>

    <?php if ($unidades): ?>
    <script>
        (function () {
            var filas = Array.prototype.slice.call(document.querySelectorAll('#cuerpo tr'));
            var campos = Array.prototype.slice.call(document.querySelectorAll('.filtros [data-col]'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');

            // Minúsculas y sin acentos para que "si" y "Sí" coincidan
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            var datos = filas.map(function (tr) {
                return {
                    tr: tr,
                    celdas: Array.prototype.map.call(tr.cells, function (td) { return norm(td.textContent); })
                };
            });

            function filtrar() {
                var activos = campos.map(function (c) {
                    return { col: +c.dataset.col, v: norm(c.value), exacto: !!c.dataset.exacto };
                }).filter(function (f) { return f.v !== ''; });
                var visibles = 0;
                datos.forEach(function (x) {
                    var ok = activos.every(function (f) {
                        return f.exacto ? x.celdas[f.col] === f.v : x.celdas[f.col].indexOf(f.v) !== -1;
                    });
                    x.tr.hidden = !ok;
                    if (ok) visibles++;
                });
                sinResultados.hidden = visibles !== 0;
                contador.textContent = visibles + ' de ' + datos.length + ' unidades de control';
            }

            campos.forEach(function (c) {
                c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrar);
            });
            filtrar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
