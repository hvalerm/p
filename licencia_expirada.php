<?php
require_once __DIR__ . '/util/verificar.php';
require_once __DIR__ . '/util/contacto.php';

iniciarSesionSegura();

if (empty($_SESSION['licencia_expirada'])) {
    header('Location: login.php');
    exit;
}

$info = $_SESSION['licencia_expirada'];
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fecha = (new DateTime($info['fecha']))->format('d/m/Y H:i');
$mensajeWa = rawurlencode('Hola, la licencia de ' . $info['empresa'] . ' expiró el ' . $fecha . '. Deseo renovar el servicio.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licencia expirada | Administrar Alojamiento</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); padding: 20px; }
        .card { width: 100%; max-width: 440px; background: #fff; border-radius: 14px; padding: 36px 32px; box-shadow: 0 10px 30px rgba(0,0,0,.3); text-align: center; }
        h1 { font-size: 1.4rem; color: #1b2a35; margin-bottom: 12px; }
        p { color: #6b7a86; line-height: 1.5; margin-bottom: 8px; }
        .fecha { color: #c0392b; font-weight: 600; }
        .acciones { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }
        .btn { display: block; padding: 13px; border-radius: 8px; color: #fff; font-weight: 600; text-decoration: none; }
        .llamar { background: #2c5364; }
        .whatsapp { background: #25d366; }
        .volver { display: inline-block; margin-top: 20px; color: #6b7a86; font-size: .9rem; }
    </style>
</head>
<body>
    <section class="card">
        <h1>Tu licencia expiró</h1>
        <p>La licencia de <strong><?= $h($info['empresa']) ?></strong> expiró el <span class="fecha"><?= $h($fecha) ?></span>.</p>
        <p>Si deseas seguir usando el servicio, por favor contáctate con tu proveedor de servicio.</p>
        <div class="acciones">
            <a class="btn llamar" href="tel:+<?= $h(PROVEEDOR_TELEFONO) ?>">Llamar por teléfono</a>
            <a class="btn whatsapp" href="https://wa.me/<?= $h(PROVEEDOR_WHATSAPP) ?>?text=<?= $mensajeWa ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
        </div>
        <a class="volver" href="login.php">Volver al inicio de sesión</a>
    </section>
</body>
</html>
