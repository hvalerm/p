<?php
require_once __DIR__ . '/../util/Seguridad/verificar.php';
requerirSesion();

$usuario = usuarioActual();
$h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

try {
    require_once __DIR__ . '/../util/BaseDatos/db.php';
    $multiempresa = count(empresasCuenta(getPDO(), (int) $_SESSION['usuario_id'])) > 1;
    $stmt = getPDO()->prepare(
        'SELECT m.idModulo, m.nombreModulo, e.idEmpresa, e.nombreEmpresa,
                (DATE(em.licenciaExpira) >= CURRENT_DATE) AS vigente
         FROM empresas_cuentas ec
         INNER JOIN empresas e ON e.idEmpresa = ec.idEmpresa
         INNER JOIN empresas_modulos em ON em.idEmpresa = e.idEmpresa
         INNER JOIN modulos m ON m.idModulo = em.idModulo
         WHERE ec.idCuenta = ? AND e.habilitado = 1 AND em.habilitado = 1
         ORDER BY m.nombreModulo, m.idModulo, e.nombreEmpresa, e.idEmpresa'
    );
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $modulos = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $asignacion) {
        $modulos[(int) $asignacion['idModulo']][] = $asignacion;
    }
} catch (PDOException | RuntimeException $e) {
    error_log('No se pudieron cargar los módulos de la empresa: ' . $e->getMessage());
    http_response_code(503);
    exit('No se pudieron cargar los módulos. Inténtalo más tarde.');
}

// Menú: [título, icono, hijos]. Hijo: [título, hijos, página, clave, icono SVG, mensaje vacío, ID de lista].
$menu = [];
$itemsConfiguracion = [];
$scriptsModulos = [];
$definicionesModulos = [];
$noDisponibles = [];
$iconoModulo = 'M3 3h8v8H3zm10 0h8v8h-8zM3 13h8v8H3zm10 0h8v8h-8z';
$carpetasModulos = scandir(__DIR__ . '/../Modulos');
if ($carpetasModulos === false) {
    error_log('No se pudo leer el directorio de módulos.');
    http_response_code(503);
    exit('No se pudieron cargar los módulos. Inténtalo más tarde.');
}
function paginasEmpresa(array $items, int $idEmpresa): array
{
    foreach ($items as &$item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item[1])) {
            $item[1] = paginasEmpresa($item[1], $idEmpresa);
        }
        if (!empty($item[2])) {
            $item[2] .= (str_contains($item[2], '?') ? '&' : '?') . 'empresa=' . $idEmpresa;
        }
    }
    return $items;
}

foreach ($modulos as $asignaciones) {
    $modulo = $asignaciones[0];
    $nombreModulo = $modulo['nombreModulo'];
    $claveModulo = 'modulo-' . (int) $modulo['idModulo'];
    $archivo = __DIR__ . '/../Modulos/' . $nombreModulo . '/menuLateral.php';
    if ($nombreModulo === '.' || $nombreModulo === '..' || preg_match('~[\\\\/\x00-\x1f\x7f]~', $nombreModulo)
        || !in_array($nombreModulo, $carpetasModulos, true) || !is_file($archivo)) {
        error_log('Módulo habilitado sin menú disponible: ' . $nombreModulo);
        $noDisponibles[$claveModulo] = true;
        $noDisponibles['configuracion/' . $claveModulo] = true;
        $grupos = [];
        if ($multiempresa || !$modulo['vigente']) {
            foreach ($asignaciones as $asignacion) {
                $grupos[] = [$asignacion['nombreEmpresa'], [], null, 'empresa-' . (int) $asignacion['idEmpresa'], $iconoModulo,
                    $asignacion['vigente'] ? 'Módulo no disponible.' : 'La licencia del módulo expiró.'];
            }
        }
        $definicionesModulos[] = [
            'principal' => [$nombreModulo, $iconoModulo, $grupos, null, $claveModulo],
            'configuracion' => [$nombreModulo, $grupos ?: null, null, $claveModulo, $iconoModulo],
        ];
        continue;
    }
    try {
        $construirMenu = require $archivo;
        if (!is_callable($construirMenu)) {
            throw new UnexpectedValueException('El archivo de menú no devuelve una función.');
        }
        $definicion = null;
        $grupos = [];
        $gruposConfiguracion = [];
        foreach ($asignaciones as $asignacion) {
            $idEmpresa = (int) $asignacion['idEmpresa'];
            $claveEmpresa = 'empresa-' . $idEmpresa;
            if (!$asignacion['vigente']) {
                $grupo = [$asignacion['nombreEmpresa'], [], null, $claveEmpresa, $iconoModulo, 'La licencia del módulo expiró.'];
                $grupos[] = $grupo;
                $gruposConfiguracion[] = $grupo;
                continue;
            }
            $asignacion['multiempresa'] = $multiempresa;
            $actualDefinicion = $construirMenu($asignacion, $nombreWeb);
            if (!is_array($actualDefinicion) || !isset($actualDefinicion['principal'], $actualDefinicion['configuracion'])) {
                throw new UnexpectedValueException('Definición de menú incompleta.');
            }
            $definicion ??= $actualDefinicion;
            $grupos[] = [$asignacion['nombreEmpresa'], paginasEmpresa($actualDefinicion['principal'][2], $idEmpresa), null, $claveEmpresa, $iconoModulo];
            $gruposConfiguracion[] = [$asignacion['nombreEmpresa'], paginasEmpresa($actualDefinicion['configuracion'][1], $idEmpresa), null, $claveEmpresa, $iconoModulo];
        }
        $definicion ??= [
            'principal' => [$nombreModulo, $iconoModulo, [], null, $claveModulo],
            'configuracion' => [$nombreModulo, [], null, $claveModulo, $iconoModulo],
        ];
        if ($multiempresa || !$asignaciones[0]['vigente']) {
            $definicion['principal'][2] = $grupos;
            $definicion['principal'][3] = null;
            $definicion['configuracion'][1] = $gruposConfiguracion;
            $definicion['configuracion'][2] = null;
        }
        $definicionesModulos[] = $definicion;
    } catch (PDOException | UnexpectedValueException $e) {
        error_log('No se pudo cargar el módulo ' . $nombreModulo . ': ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo cargar el módulo. Inténtalo más tarde.');
    }
}
usort($definicionesModulos, fn(array $a, array $b): int => ($a['prioridad'] ?? 1) <=> ($b['prioridad'] ?? 1));
foreach ($definicionesModulos as $definicion) {
    $menu[] = $definicion['principal'];
    $itemsConfiguracion[] = $definicion['configuracion'];
    if (isset($definicion['script'])) {
        $scriptsModulos[] = $definicion['script'];
    }
}
$menu[] = ['Configuración', 'M3 5h4V3h2v2h12v2H9v2H7V7H3zM3 11h12V9h2v2h4v2h-4v2h-2v-2H3zM3 17h6v-2h2v2h10v2H11v2H9v-2H3z', $itemsConfiguracion, null, 'configuracion'];
$menu[] = ['Soporte', 'M12 2a10 10 0 0 0-10 10v6h6v-8H4a8 8 0 0 1 16 0h-4v8h4a4 4 0 0 1-4 4h-4v2h4a6 6 0 0 0 6-6v-6A10 10 0 0 0 12 2z', [
    ['Contacto'], ['Enviar Correo'],
]];

function slug(string $t): string
{
    $t = strtr(mb_strtolower($t, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
}

$validas = [];
$paginas = [];
$actual = $_GET['seccion'] ?? '';

function renderItems(array $items, string $prefix, array $labels, int $depth, array &$validas, string $actual, callable $h): string
{
    global $paginas;
    $out = '';
    foreach ($items as $item) {
        if ($item === '-') {
            $out .= '<li class="sep" role="separator"></li>';
            continue;
        }
        $clave = $prefix . '/' . ($item[3] ?? slug($item[0]));
        $ruta = array_merge($labels, [$item[0]]);
        $pad = 'style="--d:' . $depth . '"';
        $icono = !empty($item[4])
            ? '<svg class="ico" viewBox="0 0 24 24" fill-rule="evenodd" aria-hidden="true"><path d="' . $h($item[4]) . '"/></svg>'
            : '';
        $listaDinamica = $item[5] ?? null;
        if ($listaDinamica !== null || !empty($item[1])) {
            $abierto = str_starts_with($actual, $clave . '/');
            $atributoLista = $listaDinamica !== null
                ? ' data-lista-modulo="' . $h($clave) . '" data-lista-id="' . $h($item[6] ?? '') . '"' : '';
            $contenido = $listaDinamica !== null && !$item[1]
                ? '<li class="sub-vacio">' . $h($listaDinamica) . '</li>'
                : renderItems($item[1], $clave, $ruta, $depth + 1, $validas, $actual, $h);
            $out .= '<li class="group' . ($abierto ? ' open' : '') . '"><button type="button" class="item-btn" ' . $pad . ' aria-expanded="' . ($abierto ? 'true' : 'false') . '">'
                . $icono
                . '<span class="label">' . $h($item[0]) . '</span>'
                . '<svg class="arrow" viewBox="0 0 24 24"><path d="M8 5l8 7-8 7z"/></svg></button>'
                . '<div class="sub"><ul' . $atributoLista . '>' . $contenido . '</ul></div></li>';
        } else {
            $validas[$clave] = $ruta;
            if (!empty($item[2])) {
                $paginas[$clave] = $item[2];
            }
            $out .= '<li><a href="?seccion=' . $h($clave) . '" ' . $pad . ' class="' . ($actual === $clave ? 'active' : '') . '">'
                . $icono . '<span class="label">' . $h($item[0]) . '</span></a></li>';
        }
    }
    return $out;
}

$navHtml = '';
foreach ($menu as $entrada) {
    [$titulo, $icono, $hijos] = $entrada;
    $pagina = $entrada[3] ?? null;
    $clave = $entrada[4] ?? slug($titulo);
    if (!$hijos && $clave !== 'configuracion') {
        // Menú de acceso directo, sin submenús
        $validas[$clave] = [$titulo];
        if (!empty($pagina)) {
            $paginas[$clave] = $pagina;
        }
        $navHtml .= '<div class="group"><a href="?seccion=' . $h($clave) . '" class="item-btn' . ($actual === $clave ? ' active' : '') . '">'
            . '<svg class="ico" viewBox="0 0 24 24" fill-rule="evenodd"><path d="' . $h($icono) . '"/></svg>'
            . '<span class="label">' . $h($titulo) . '</span></a></div>';
        continue;
    }
    $abierto = str_starts_with($actual, $clave . '/');
    $independiente = $clave === 'configuracion' ? ' data-acordeon-independiente' : '';
    $navHtml .= '<div class="group' . ($abierto ? ' open' : '') . '"' . $independiente . '><button type="button" class="item-btn" aria-expanded="' . ($abierto ? 'true' : 'false') . '">'
        . '<svg class="ico" viewBox="0 0 24 24" fill-rule="evenodd"><path d="' . $h($icono) . '"/></svg>'
        . '<span class="label">' . $h($titulo) . '</span>'
        . '<svg class="arrow" viewBox="0 0 24 24"><path d="M8 5l8 7-8 7z"/></svg></button>'
        . '<div class="sub"><ul>' . ($hijos
            ? renderItems($hijos, $clave, [$titulo], 1, $validas, $actual, $h)
            : '<li class="sub-vacio">No hay módulos asignados a esta empresa.</li>') . '</ul></div></div>';
}

$validas['configuracion-cuenta'] = ['Configuración de cuenta'];
if (!isset($validas[$actual])) {
    $actual = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modo Anfitrión | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../ElementosVistaBasica/img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: #f2f5f7; color: #1b2a35; min-height: 100vh; }

        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #0f2027, #203a43 60%, #2c5364);
            color: #dbe7ee;
            display: flex; flex-direction: column;
            transition: width .25s ease;
            overflow: hidden;
            z-index: 10;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-bottom: 1px solid rgba(255,255,255,.1); white-space: nowrap; }
        .brand .logo { flex: 0 0 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #2c5364, #3a8fb7); display: flex; align-items: center; justify-content: center; }
        .brand svg { width: 22px; height: 22px; fill: #fff; }
        .brand span { font-weight: 600; }
        .brand-link { color: inherit; text-decoration: none; }
        .brand-link:hover { text-decoration: underline; }

        nav { flex: 1; padding: 12px 8px; overflow-y: auto; }
        .item-btn, .sub a {
            display: flex; align-items: center; gap: 12px;
            width: 100%; padding: 12px; border: none; background: none;
            color: inherit; font: inherit; text-align: left; text-decoration: none;
            border-radius: 8px; cursor: pointer; white-space: nowrap;
        }
        .item-btn:hover, .sub a:hover { background: rgba(255,255,255,.1); }
        .item-btn .ico, .sub a .ico { flex: 0 0 22px; width: 22px; height: 22px; fill: currentColor; }
        .item-btn .label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
        .sub .label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
        .item-btn .arrow { width: 16px; height: 16px; fill: currentColor; transition: transform .2s; }
        .group.open > .item-btn .arrow { transform: rotate(90deg); }
        .sub { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .25s ease; }
        .sub > ul { overflow: hidden; min-height: 0; list-style: none; }
        .group.open > .sub { grid-template-rows: 1fr; }
        .sub .item-btn, .sub a { font-size: .92rem; color: #b9cad5; padding-left: calc(46px + (var(--d, 1) - 1) * 16px); }
        .sub-vacio { padding: 8px 12px 12px 46px; color: #b9cad5; font-size: .85rem; }
        .sub a.active { background: rgba(58,143,183,.35); color: #fff; }
        a.item-btn.active { background: rgba(58,143,183,.35); color: #fff; }
        .sep { height: 1px; margin: 6px 12px 6px 46px; background: rgba(255,255,255,.2); }

        .user { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.1); font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user .item-btn { padding: 6px 8px; }
        .user > .item-btn { margin-top: 6px; }

        .main { margin-left: 260px; transition: margin-left .25s ease; }
        .topbar { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .topbar strong { min-width: 0; overflow-wrap: anywhere; }
        .toggle { background: none; border: none; cursor: pointer; padding: 6px; border-radius: 6px; }
        .toggle:hover { background: #e8eef2; }
        .toggle svg { width: 24px; height: 24px; fill: #34444f; display: block; }
        .content { padding: 28px 24px; }
        .embebida { width: 100%; height: calc(100vh - 130px); border: none; border-radius: 12px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .panel { background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .panel h2 { margin-bottom: 8px; }
        .panel p { color: #6b7a86; }

        /* Barra contraída */
        body.collapsed .sidebar { width: 68px; }
        body.collapsed .main { margin-left: 68px; }
        body.collapsed .label, body.collapsed .arrow, body.collapsed .brand span, body.collapsed .user { display: none; }
        body.collapsed .sub { grid-template-rows: 0fr !important; }

        .cerrar-menu { display: none; margin-left: auto; background: rgba(255,255,255,.12); border: none; color: #fff; width: 36px; height: 36px; border-radius: 8px; font-size: 1.5rem; line-height: 1; cursor: pointer; }
        .cerrar-menu:hover { background: rgba(255,255,255,.22); }
        .fondo-menu { display: none; }

        @media (max-width: 768px) {
            .cerrar-menu { display: block; }
            body.collapsed .fondo-menu { display: block; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 9; }
            .sidebar { width: 0; }
            .main, body.collapsed .main { margin-left: 0; }
            body.collapsed .sidebar { width: 260px; }
            body.collapsed .sub { grid-template-rows: 0fr !important; }
            body.collapsed .group.open > .sub { grid-template-rows: 1fr !important; }
            body.collapsed .label, body.collapsed .arrow, body.collapsed .brand span, body.collapsed .user { display: initial; }
        }
        .guia { list-style: none; margin-top: 18px; font-size: .95rem; }
        .guia ul { list-style: none; margin-left: 12px; padding-left: 16px; border-left: 2px solid #d5dee4; }
        .guia li { margin: 8px 0; line-height: 1.4; }
        .guia ul li { position: relative; }
        .guia ul li::before { content: ''; position: absolute; left: -16px; top: .75em; width: 12px; border-top: 2px solid #d5dee4; }
        .guia b { color: #1b2a35; }
        .guia span { color: #6b7a86; }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="logo"><svg viewBox="0 0 24 24"><path d="M3 21V9l9-6 9 6v12h-6v-7H9v7H3z"/></svg></div>
            <span><a href="vistaAnfitrion.php" class="brand-link">Modo Anfitrión</a></span>
            <button type="button" class="cerrar-menu" id="btnCerrarMenu" aria-label="Cerrar menú">&times;</button>
        </div>
        <nav>
            <?= $navHtml ?>
        </nav>
        <div class="user">
            <?= $h($usuario['nombre']) ?>
            <form method="post" action="../logout.php">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <button type="submit" class="item-btn">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <div class="fondo-menu" id="fondoMenu"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="toggle" id="btnToggle" aria-label="Mostrar u ocultar menú">
                <svg viewBox="0 0 24 24"><path d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
            </button>
            <strong><?= $h($actual !== '' ? implode(' › ', $validas[$actual]) : 'Administrar Alojamiento') ?></strong>
        </header>
        <main class="content">
            <p style="margin-bottom: 16px; color: #526977;">El calendario permite seleccionar fechas, pero no guarda reservas.</p>
            <?php if ($actual && isset($paginas[$actual])): ?>
                <iframe class="embebida" allow="camera" title="<?= $h(end($validas[$actual])) ?>" src="<?= $h($paginas[$actual] . (str_contains($paginas[$actual], '?') ? '&' : '?') . 'embed=1') ?>"></iframe>
            <?php else: ?>
            <section class="panel">
                <?php if ($actual): ?>
                    <h2><?= $h(implode(' › ', $validas[$actual])) ?></h2>
                    <p><?= isset($noDisponibles[$actual]) ? 'Módulo no disponible.' : 'Sección en construcción.' ?></p>
                <?php else: ?>
                    <h2>Bienvenido, <?= $h($usuario['nombre']) ?></h2>
                    <p>Selecciona una opción del menú lateral.</p>
                    <ul class="guia">
                        <?php foreach ($definicionesModulos as $moduloGuia): ?>
                            <?php if (isset($moduloGuia['guia'])): ?>
                                <?php require $moduloGuia['guia']; ?>
                            <?php else: ?>
                                <li><b><?= $h($moduloGuia['principal'][0]) ?></b></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <li>⚙️ <b>Configuración</b> — <span>Despliega los módulos asignados a tu empresa en el menú lateral.</span></li>
                        <li>🎧 <b>Soporte</b> — <span>Estamos aquí para ayudarte.</span>
                            <ul>
                                <li><b>Contacto</b> — <span>Encuentra cómo comunicarte con nosotros.</span></li>
                                <li><b>Enviar Correo</b> — <span>Escríbenos para solicitar ayuda.</span></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <?php foreach ($scriptsModulos as $scriptModulo): ?>
    <script src="<?= $h($scriptModulo) ?>"></script>
    <?php endforeach; ?>
    <script>
        var body = document.body;

        document.querySelector('nav').addEventListener('click', function (e) {
            var btn = e.target.closest('.item-btn');
            if (!btn || btn.tagName === 'A') return;
            var grupo = btn.parentElement;
            var esRaiz = grupo.parentElement.tagName === 'NAV';
            var movil = window.matchMedia('(max-width: 768px)').matches;
            if (esRaiz && !movil && body.classList.contains('collapsed')) body.classList.remove('collapsed');
            if (!grupo.classList.contains('open') && !grupo.hasAttribute('data-acordeon-independiente')) {
                Array.from(grupo.parentElement.children).forEach(function (g) {
                    if (g === grupo || !g.matches('.group.open:not([data-acordeon-independiente])')) return;
                    g.classList.remove('open');
                    g.querySelector(':scope > .item-btn').setAttribute('aria-expanded', 'false');
                });
            }
            var abierto = grupo.classList.toggle('open');
            btn.setAttribute('aria-expanded', abierto);
        });

        document.getElementById('btnToggle').addEventListener('click', function () {
            body.classList.toggle('collapsed');
        });

        function cerrarMenuMovil() { body.classList.remove('collapsed'); }
        document.getElementById('btnCerrarMenu').addEventListener('click', cerrarMenuMovil);
        document.getElementById('fondoMenu').addEventListener('click', cerrarMenuMovil);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && window.matchMedia('(max-width: 768px)').matches) cerrarMenuMovil();
        });
    </script>
</body>
</html>
