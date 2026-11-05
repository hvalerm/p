<?php
// Piezas comunes de las pantallas de recuperación de contraseña.

const REC_CODIGO_MINUTOS = 5;
const REC_VERIFICADO_MINUTOS = 10;
const REC_MAX_INTENTOS = 10;
const REC_ESPERA_SEGUNDOS = 60;
const REC_PASSWORD_MIN = 8;
const REC_PASSWORD_MAX = 200;

function recEscapar($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function recCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function recCsrfValido(): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
}

function recRedirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function recInicio(string $titulo, string $subtitulo, string $error = '', string $info = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= recEscapar($titulo) ?> | OpenDoors</title>
    <link rel="icon" type="image/png" href="img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); padding: 20px; }
        .card { width: 100%; max-width: 400px; background: #fff; border-radius: 14px; padding: 40px 36px; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
        .logo { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 16px; background: linear-gradient(135deg, #2c5364, #3a8fb7); display: flex; align-items: center; justify-content: center; }
        .logo svg { width: 34px; height: 34px; fill: #fff; }
        h1 { text-align: center; font-size: 1.4rem; color: #1b2a35; }
        .sub { text-align: center; color: #6b7a86; font-size: .9rem; margin: 6px 0 26px; }
        label { display: block; font-size: .85rem; font-weight: 600; color: #34444f; margin-bottom: 6px; }
        .field { margin-bottom: 18px; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: 12px 14px; border: 1px solid #cfd8dc; border-radius: 8px; font-size: .95rem; }
        input:focus { outline: none; border-color: #3a8fb7; box-shadow: 0 0 0 3px rgba(58,143,183,.2); }
        input.codigo { letter-spacing: .3em; text-align: center; font-size: 1.2rem; }
        button.submit { width: 100%; padding: 13px; border: none; border-radius: 8px; background: linear-gradient(135deg, #2c5364, #3a8fb7); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button.submit:hover { opacity: .92; }
        .error { background: #fdecea; color: #b3261e; border: 1px solid #f5c2be; padding: 10px 12px; border-radius: 8px; font-size: .88rem; margin-bottom: 18px; }
        .info { background: #e8f5ec; color: #17632c; border: 1px solid #a9d8b8; padding: 10px 12px; border-radius: 8px; font-size: .88rem; margin-bottom: 18px; }
        .volver { display: block; text-align: center; margin-top: 18px; font-size: .88rem; color: #2c6f94; text-decoration: none; }
        .volver:hover { text-decoration: underline; }
        .ayuda { font-size: .8rem; color: #6b7a86; margin-top: 4px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 21V9l9-6 9 6v12h-6v-7H9v7H3z"/></svg></div>
        <h1><?= recEscapar($titulo) ?></h1>
        <p class="sub"><?= recEscapar($subtitulo) ?></p>
        <?php if ($error): ?><div class="error" role="alert"><?= recEscapar($error) ?></div><?php endif; ?>
        <?php if ($info): ?><div class="info" role="status"><?= recEscapar($info) ?></div><?php endif; ?>
<?php
}

function recFin(): void
{
    ?>
        <a class="volver" href="login.php">Volver a iniciar sesión</a>
    </main>
</body>
</html>
<?php
}
