<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$locales = [];
$error = '';

try {
    $stmt = getPDO()->prepare(
        'SELECT l.id_local, l.nombre_local, l.direccion_local, l.habilitado
         FROM locales l
         INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
         WHERE c.id_cuenta = ? AND l.eliminado = 0
         ORDER BY l.nombre_local, l.id_local'
    );
    $stmt->execute([$_SESSION['usuario_id']]);
    $locales = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en SeleccionarLocalAcceso: ' . $e->getMessage());
    $error = 'No se pudieron cargar los locales. Inténtalo más tarde.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seleccionar local | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 6px; }
        .sub { color: #6b7a86; margin-bottom: 18px; }
        .filtros { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px; }
        .filtros label { display: block; font-size: .85rem; color: #6b7a86; margin-bottom: 4px; }
        .filtros input { width: 100%; padding: 10px 12px; border: 1px solid #cfd8de; border-radius: 8px; font: inherit; }
        .filtros input:focus { outline: 2px solid #3a8fb7; border-color: transparent; }
        .contador { font-size: .85rem; color: #6b7a86; margin: 10px 0 14px; }
        ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        li a { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px solid #e1e8ec; border-radius: 10px; color: inherit; text-decoration: none; }
        li a:hover { background: #f2f7fa; border-color: #3a8fb7; }
        .nombre { font-weight: 600; }
        .dir { color: #6b7a86; font-size: .9rem; margin-top: 2px; }
        .badge { flex: none; font-size: .75rem; padding: 3px 8px; border-radius: 99px; background: #fdecea; color: #b3261e; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Crear acceso</h1>
        <p class="sub">Selecciona el local para el que deseas crear el acceso.</p>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$locales): ?>
            <p class="vacio">Tu empresa no tiene locales registrados.</p>
        <?php else: ?>
            <div class="filtros">
                <div>
                    <label for="fNombre">Nombre</label>
                    <input type="search" id="fNombre" placeholder="Filtrar por nombre" autocomplete="off" autofocus>
                </div>
                <div>
                    <label for="fDireccion">Dirección</label>
                    <input type="search" id="fDireccion" placeholder="Filtrar por dirección" autocomplete="off">
                </div>
            </div>
            <p class="contador" id="contador" aria-live="polite"></p>
            <ul id="lista">
                <?php foreach ($locales as $l): ?>
                    <li data-nombre="<?= $h($l['nombre_local']) ?>" data-direccion="<?= $h($l['direccion_local']) ?>">
                        <a href="localSeleccionado.php?id=<?= (int) $l['id_local'] ?><?= $embed ? '&amp;embed=1' : '' ?>">
                            <div>
                                <div class="nombre"><?= $h($l['nombre_local']) ?></div>
                                <div class="dir"><?= $h($l['direccion_local']) ?></div>
                            </div>
                            <?php if (!$l['habilitado']): ?><span class="badge">Deshabilitado</span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="vacio" id="sinResultados" hidden>Ningún local coincide con los filtros.</p>
        <?php endif; ?>
    </section>

    <?php if ($locales): ?>
    <script>
        (function () {
            var fNombre = document.getElementById('fNombre');
            var fDireccion = document.getElementById('fDireccion');
            var items = Array.prototype.slice.call(document.querySelectorAll('#lista li'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');

            // Minúsculas y sin acentos para que "cafe" encuentre "Café"
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            var datos = items.map(function (li) {
                return { li: li, nombre: norm(li.dataset.nombre), direccion: norm(li.dataset.direccion) };
            });

            function filtrar() {
                var n = norm(fNombre.value), d = norm(fDireccion.value), visibles = 0;
                datos.forEach(function (x) {
                    var ok = x.nombre.indexOf(n) !== -1 && x.direccion.indexOf(d) !== -1;
                    x.li.hidden = !ok;
                    if (ok) visibles++;
                });
                sinResultados.hidden = visibles !== 0;
                contador.textContent = visibles + ' de ' + datos.length + ' locales';
            }

            fNombre.addEventListener('input', filtrar);
            fDireccion.addEventListener('input', filtrar);
            filtrar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
