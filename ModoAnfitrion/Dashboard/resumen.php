<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$fmt = fn($f) => $f ? date('d/m/Y H:i', strtotime($f)) : '';
$error = '';
$k = ['locales' => 0, 'locales_ok' => 0, 'vigentes' => 0, 'proximos' => 0, 'usados' => 0, 'revocados' => 0,
      'disp' => 0, 'disp_activos' => 0, 'aperturas' => 0, 'puertas_pendientes' => 0];
$proximos = [];
$noUsados = [];
$k['no_usados'] = 0;
$alertas = [];
$porLocal = [];

try {
    $pdo = getPDO();
    $q = $pdo->prepare('SELECT id_empresa FROM cuentas WHERE id_cuenta = ?');
    $q->execute([$_SESSION['usuario_id']]);
    $idEmpresa = (int) $q->fetchColumn();

    // Locales
    $q = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(habilitado = 1), 0) AS ok, COALESCE(SUM(accionar = 1), 0) AS pend
                        FROM locales WHERE id_empresa = ? AND eliminado = 0');
    $q->execute([$idEmpresa]);
    $r = $q->fetch();
    $k['locales'] = (int) $r['total'];
    $k['locales_ok'] = (int) $r['ok'];
    $k['puertas_pendientes'] = (int) $r['pend'];

    // Accesos por situación
    $q = $pdo->prepare(
        'SELECT COALESCE(SUM(habilitado = 1 AND ejecutado = 0 AND NOW() BETWEEN activo_desde AND activo_hasta), 0) AS vigentes,
                COALESCE(SUM(habilitado = 1 AND ejecutado = 0 AND activo_desde > NOW()), 0) AS proximos,
                COALESCE(SUM(ejecutado = 1), 0) AS usados,
                COALESCE(SUM(habilitado = 0 AND ejecutado = 0), 0) AS revocados
         FROM accesos WHERE id_empresa = ?'
    );
    $q->execute([$idEmpresa]);
    $r = $q->fetch();
    foreach (['vigentes', 'proximos', 'usados', 'revocados'] as $c) {
        $k[$c] = (int) $r[$c];
    }

    // Próximas llegadas y accesos vigentes sin usar
    $q = $pdo->prepare(
        'SELECT l.nombre_local, a.activo_desde, a.activo_hasta, (a.activo_desde > NOW()) AS futuro
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         WHERE a.id_empresa = ? AND a.habilitado = 1 AND a.ejecutado = 0 AND a.activo_hasta >= NOW()
         ORDER BY a.activo_desde LIMIT 5'
    );
    $q->execute([$idEmpresa]);
    $proximos = $q->fetchAll();

    // Accesos vencidos que nunca se usaron
    $q = $pdo->prepare(
        'SELECT l.nombre_local, a.activo_desde, a.activo_hasta
         FROM accesos a
         INNER JOIN locales l ON l.id_local = a.id_local AND l.id_empresa = a.id_empresa
         WHERE a.id_empresa = ? AND a.habilitado = 1 AND a.ejecutado = 0 AND a.activo_hasta < NOW()
         ORDER BY a.activo_hasta DESC'
    );
    $q->execute([$idEmpresa]);
    $noUsados = $q->fetchAll();
    $k['no_usados'] = count($noUsados);
    $noUsados = array_slice($noUsados, 0, 5);

    // Dispositivos y aperturas por local
    $q = $pdo->prepare(
        'SELECT l.nombre_local, m.id_microcontrolador, m.habilitado, m.cantidadAperturas,
                TIMESTAMPDIFF(SECOND, m.ultimaConsulta, NOW()) AS seg
         FROM microcontroladores m
         INNER JOIN locales l ON l.id_local = m.id_local AND l.id_empresa = m.id_empresa
         WHERE m.id_empresa = ? ORDER BY m.cantidadAperturas DESC, l.nombre_local'
    );
    $q->execute([$idEmpresa]);
    foreach ($q->fetchAll() as $d) {
        $k['disp']++;
        $k['aperturas'] += (int) $d['cantidadAperturas'];
        $activo = $d['seg'] !== null && (int) $d['seg'] <= 15;
        if ($activo) {
            $k['disp_activos']++;
        } elseif ($d['habilitado']) {
            $alertas[] = 'El dispositivo de «' . $d['nombre_local'] . '» no responde. Revisa que esté encendido y con internet.';
        }
        $porLocal[] = $d;
    }

    // Locales habilitados sin dispositivo
    $q = $pdo->prepare(
        'SELECT l.nombre_local FROM locales l
         WHERE l.id_empresa = ? AND l.eliminado = 0 AND l.habilitado = 1
           AND NOT EXISTS (SELECT 1 FROM microcontroladores m WHERE m.id_local = l.id_local AND m.id_empresa = l.id_empresa)'
    );
    $q->execute([$idEmpresa]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $alertas[] = '«' . $n . '» no tiene dispositivo de apertura, sus huéspedes no podrán abrir la puerta.';
    }
} catch (PDOException $e) {
    error_log('Error en resumen: ' . $e->getMessage());
    $error = 'No se pudo cargar el resumen.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumen | OpenDoors</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin-bottom: 4px; }
        .sub { color: #6b7a86; margin-bottom: 20px; }
        h2 { font-size: 1rem; margin-bottom: 12px; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .tarjeta { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border-left: 5px solid #3a8fb7; }
        .tarjeta .n { font-size: 2rem; font-weight: 700; line-height: 1.1; }
        .tarjeta .t { font-weight: 600; margin-top: 4px; }
        .tarjeta .d { font-size: .82rem; color: #6b7a86; margin-top: 2px; }
        .verde { border-left-color: #1e8e3e; } .naranja { border-left-color: #e08a00; } .rojo { border-left-color: #c0392b; } .gris { border-left-color: #8a98a3; }
        .caja { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
        .dos { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .alerta { background: #fff4e5; border: 1px solid #f0c987; color: #7a4a00; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; font-size: .9rem; }
        .ok { background: #e8f5ec; border: 1px solid #a9d8b8; color: #17632c; border-radius: 8px; padding: 10px 14px; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #e1e8ec; }
        th { color: #6b7a86; font-weight: 600; background: #f7f9fa; }
        td.num, th.num { text-align: right; }
        .vacio { color: #6b7a86; padding: 8px 0; }
        .chip { display: inline-block; font-size: .75rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .chip.ahora { background: #e8f5ec; color: #1e8e3e; } .chip.luego { background: #e8f1f7; color: #2c6f94; }
        .a { color: #1e8e3e; font-weight: 700; } .i { color: #c0392b; font-weight: 700; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Resumen</h1>
        <p class="sub">Lo más importante de tu negocio, de un vistazo.</p>

        <?php if ($error): ?>
            <div class="caja"><p class="vacio"><?= $h($error) ?></p></div>
        <?php else: ?>
            <div class="caja">
                <h2>Atención</h2>
                <?php foreach ($alertas as $a): ?><div class="alerta">⚠️ <?= $h($a) ?></div><?php endforeach; ?>
                <?php if ($k['puertas_pendientes']): ?>
                    <div class="alerta">🚪 Hay <?= $k['puertas_pendientes'] ?> orden(es) de apertura esperando a ser ejecutada(s) por el dispositivo.</div>
                <?php endif; ?>
                <?php if (!$alertas && !$k['puertas_pendientes']): ?>
                    <div class="ok">✅ Todo en orden. No hay nada que requiera tu atención.</div>
                <?php endif; ?>
            </div>

            <div class="tarjetas">
                <div class="tarjeta"><div class="n"><?= $k['locales_ok'] ?>/<?= $k['locales'] ?></div><div class="t">Locales habilitados</div><div class="d">Alojamientos listos para recibir huéspedes</div></div>
                <div class="tarjeta <?= $k['disp'] && $k['disp_activos'] === $k['disp'] ? 'verde' : 'rojo' ?>"><div class="n"><?= $k['disp_activos'] ?>/<?= $k['disp'] ?></div><div class="t">Dispositivos activos</div><div class="d">Equipos conectados ahora mismo</div></div>
                <div class="tarjeta verde"><div class="n"><?= $k['vigentes'] ?></div><div class="t">Accesos vigentes</div><div class="d">Huéspedes que pueden entrar ahora</div></div>
                <div class="tarjeta naranja"><div class="n"><?= $k['proximos'] ?></div><div class="t">Próximos accesos</div><div class="d">Llegadas programadas</div></div>
                <div class="tarjeta gris"><div class="n"><?= $k['usados'] ?></div><div class="t">Accesos usados</div><div class="d">Huéspedes que ya abrieron</div></div>
                <div class="tarjeta <?= $k['no_usados'] ? 'rojo' : 'verde' ?>"><div class="n"><?= $k['no_usados'] ?></div><div class="t">Accesos sin usar</div>                <div class="d">Vencieron sin que el huésped abriera</div></div>
                                <div class="tarjeta"><div class="n"><?= $k['aperturas'] ?></div><div class="t">Aperturas totales</div><div class="d">Veces que se ha abierto una puerta</div></div>
            </div>

            <div class="dos">
                <div class="caja">
                    <h2>Próximas llegadas</h2>
                    <?php if (!$proximos): ?>
                        <p class="vacio">No hay accesos pendientes. Crea uno en Accesos → Crear.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Local</th><th>Desde</th><th>Hasta</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($proximos as $p): ?>
                                <tr>
                                    <td><?= $h($p['nombre_local']) ?></td>
                                    <td><?= $h($fmt($p['activo_desde'])) ?></td>
                                    <td><?= $h($fmt($p['activo_hasta'])) ?></td>
                                    <td><?= $p['futuro'] ? '<span class="chip luego">Próximo</span>' : '<span class="chip ahora">Vigente</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="caja">
                    <h2>Aperturas por local</h2>
                    <?php if (!$porLocal): ?>
                        <p class="vacio">Aún no tienes dispositivos de apertura registrados.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Local</th><th>Estado</th><th class="num">Aperturas</th></tr></thead>
                            <tbody>
                            <?php foreach ($porLocal as $d): $act = $d['seg'] !== null && (int) $d['seg'] <= 15; ?>
                                <tr>
                                    <td><?= $h($d['nombre_local']) ?></td>
                                    <td class="<?= $act ? 'a' : 'i' ?>"><?= $act ? 'Activo' : 'Inactivo' ?></td>
                                    <td class="num"><?= (int) $d['cantidadAperturas'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="caja">
                <h2>Accesos que no se usaron<?= $k['no_usados'] > 5 ? ' (últimos 5 de ' . $k['no_usados'] . ')' : '' ?></h2>
                <?php if (!$noUsados): ?>
                    <p class="vacio">Ningún acceso venció sin usarse. ¡Bien!</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Local</th><th>Desde</th><th>Venció</th></tr></thead>
                        <tbody>
                        <?php foreach ($noUsados as $n): ?>
                            <tr>
                                <td><?= $h($n['nombre_local']) ?></td>
                                <td><?= $h($fmt($n['activo_desde'])) ?></td>
                                <td><?= $h($fmt($n['activo_hasta'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($k['revocados']): ?>
                <p class="vacio"><?= $k['revocados'] ?> acceso(s) revocado(s) sin usar.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</body>
</html>
