<?php
require_once __DIR__ . '/../util/verificar.php';
requerirSesion();

$usuario = usuarioActual();
$h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// Locales de la empresa a la que pertenece la cuenta (sin los eliminados)
$itemsLocales = [];
try {
    require_once __DIR__ . '/../util/db.php';
    $stmt = getPDO()->prepare(
        'SELECT l.id_local, l.nombre_local
         FROM locales l
         INNER JOIN cuentas c ON c.id_empresa = l.id_empresa
         WHERE c.id_cuenta = ? AND l.eliminado = 0
         ORDER BY l.nombre_local, l.id_local'
    );
    $stmt->execute([$usuario['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $local) {
        $itemsLocales[] = [$local['nombre_local'], null, 'OpenDoors/localSeleccionado.php?id=' . $local['id_local'], 'local-' . $local['id_local']];
    }
} catch (Throwable $e) {
    error_log('No se pudieron cargar los locales: ' . $e->getMessage());
}
if (!$itemsLocales) {
    $itemsLocales[] = ['Sin locales'];
}

// Estructura del menú: [título, ícono, [hijos]]. Hijo: [título, [hijos], página, clave] u hoja [título]; '-' es un separador.
$menu = [
    ['OpenDoors', 'M5 3h14v18H5zm9 8a1 1 0 1 0 0 2 1 1 0 0 0 0-2z', $itemsLocales],
    ['Accesos', 'M12.65 10A6 6 0 1 0 12.65 14H17v4h4v-4h2v-4zM7 14a2 2 0 1 1 0-4 2 2 0 0 1 0 4z', [
        ['Crear', null, 'Accesos/SeleccionarLocalAcceso.php'], ['Listar', null, 'Accesos/listarAccesos.php'], ['Revocar', null, 'Accesos/busquedaAccesoRevocar.php'],
    ]],
    ['Unidades de control', 'M6 6h12v12H6zM9 9v6h6V9zM7 3h2v3H7zm4 0h2v3h-2zm4 0h2v3h-2zM7 18h2v3H7zm4 0h2v3h-2zm4 0h2v3h-2z', [
        ['Ver estado'], ['Ver actividad'],
    ]],
];

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
        if (!empty($item[1])) {
            $abierto = str_starts_with($actual, $clave . '/');
            $out .= '<li class="group' . ($abierto ? ' open' : '') . '"><button type="button" class="item-btn" ' . $pad . ' aria-expanded="' . ($abierto ? 'true' : 'false') . '">'
                . '<span class="label">' . $h($item[0]) . '</span>'
                . '<svg class="arrow" viewBox="0 0 24 24"><path d="M8 5l8 7-8 7z"/></svg></button>'
                . '<div class="sub"><ul>' . renderItems($item[1], $clave, $ruta, $depth + 1, $validas, $actual, $h) . '</ul></div></li>';
        } else {
            $validas[$clave] = $ruta;
            if (!empty($item[2])) {
                $paginas[$clave] = $item[2];
            }
            $out .= '<li><a href="?seccion=' . $h($clave) . '" ' . $pad . ' class="' . ($actual === $clave ? 'active' : '') . '">' . $h($item[0]) . '</a></li>';
        }
    }
    return $out;
}

$navHtml = '';
foreach ($menu as [$titulo, $icono, $hijos]) {
    $clave = slug($titulo);
    $abierto = str_starts_with($actual, $clave . '/');
    $navHtml .= '<div class="group' . ($abierto ? ' open' : '') . '"><button type="button" class="item-btn" aria-expanded="' . ($abierto ? 'true' : 'false') . '">'
        . '<svg class="ico" viewBox="0 0 24 24" fill-rule="evenodd"><path d="' . $h($icono) . '"/></svg>'
        . '<span class="label">' . $h($titulo) . '</span>'
        . '<svg class="arrow" viewBox="0 0 24 24"><path d="M8 5l8 7-8 7z"/></svg></button>'
        . '<div class="sub"><ul>' . renderItems($hijos, $clave, [$titulo], 1, $validas, $actual, $h) . '</ul></div></div>';
}

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
    <link rel="icon" type="image/png" href="../img/OpenDoors.png">
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

        nav { flex: 1; padding: 12px 8px; overflow-y: auto; }
        .item-btn, .sub a {
            display: flex; align-items: center; gap: 12px;
            width: 100%; padding: 12px; border: none; background: none;
            color: inherit; font: inherit; text-align: left; text-decoration: none;
            border-radius: 8px; cursor: pointer; white-space: nowrap;
        }
        .item-btn:hover, .sub a:hover { background: rgba(255,255,255,.1); }
        .item-btn svg.ico { flex: 0 0 22px; width: 22px; height: 22px; fill: currentColor; }
        .item-btn .label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
        .item-btn .arrow { width: 16px; height: 16px; fill: currentColor; transition: transform .2s; }
        .group.open > .item-btn .arrow { transform: rotate(90deg); }
        .sub { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .25s ease; }
        .sub > ul { overflow: hidden; min-height: 0; list-style: none; }
        .group.open > .sub { grid-template-rows: 1fr; }
        .sub .item-btn, .sub a { font-size: .92rem; color: #b9cad5; padding-left: calc(46px + (var(--d, 1) - 1) * 16px); }
        .sub a.active { background: rgba(58,143,183,.35); color: #fff; }
        .sep { height: 1px; margin: 6px 12px 6px 46px; background: rgba(255,255,255,.2); }

        .user { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.1); font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .main { margin-left: 260px; transition: margin-left .25s ease; }
        .topbar { display: flex; align-items: center; gap: 14px; padding: 14px 24px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
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
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="logo"><svg viewBox="0 0 24 24"><path d="M3 21V9l9-6 9 6v12h-6v-7H9v7H3z"/></svg></div>
            <span>Modo Anfitrión</span>
            <button type="button" class="cerrar-menu" id="btnCerrarMenu" aria-label="Cerrar menú">&times;</button>
        </div>
        <nav>
            <?= $navHtml ?>
        </nav>
        <div class="user">
            <?= $h($usuario['nombre']) ?>
            <form method="post" action="../logout.php" style="margin-top:6px">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
                <button type="submit" class="item-btn" style="padding:6px 8px;font-size:.85rem">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <div class="fondo-menu" id="fondoMenu"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="toggle" id="btnToggle" aria-label="Mostrar u ocultar menú">
                <svg viewBox="0 0 24 24"><path d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
            </button>
            <strong>Administrar Alojamiento</strong>
        </header>
        <main class="content">
            <?php if ($actual && isset($paginas[$actual])): ?>
                <iframe class="embebida" allow="camera" title="<?= $h(end($validas[$actual])) ?>" src="<?= $h($paginas[$actual] . (str_contains($paginas[$actual], '?') ? '&' : '?') . 'embed=1') ?>"></iframe>
            <?php else: ?>
            <section class="panel">
                <?php if ($actual): ?>
                    <h2><?= $h(implode(' › ', $validas[$actual])) ?></h2>
                    <p>Sección en construcción.</p>
                <?php else: ?>
                    <h2>Bienvenido, <?= $h($usuario['nombre']) ?></h2>
                    <p>Selecciona una opción del menú lateral.</p>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <script>
        var body = document.body;

        document.querySelector('nav').addEventListener('click', function (e) {
            var btn = e.target.closest('.item-btn');
            if (!btn) return;
            var grupo = btn.parentElement;
            var esRaiz = grupo.parentElement.tagName === 'NAV';
            var movil = window.matchMedia('(max-width: 768px)').matches;
            if (esRaiz && !movil && body.classList.contains('collapsed')) body.classList.remove('collapsed');
            if (esRaiz && !grupo.classList.contains('open')) {
                document.querySelectorAll('nav > .group.open').forEach(function (g) {
                    g.classList.remove('open');
                    g.querySelector('.item-btn').setAttribute('aria-expanded', 'false');
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
