<?php
require_once __DIR__ . '/util/verificar.php';
require __DIR__ . '/util/db.php';

iniciarSesionSegura();

if (haySesionActiva()) {
    header('Location: ModoAnfitrion/vistaAnfitrion.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = isset($_GET['expirada']) ? 'Tu sesión expiró. Inicia sesión nuevamente.' : '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
    } elseif ($usuario === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        try {
            $stmt = getPDO()->prepare(
                'SELECT c.id_cuenta AS id,
                        COALESCE(NULLIF(TRIM(c.nombre_cuenta), \'\'), c.correo_cuenta) AS nombre,
                        c.pwd AS password,
                        e.nombre_empresa,
                        e.licencia_expira
                 FROM cuentas c
                 INNER JOIN empresas e ON e.id_empresa = c.id_empresa
                 WHERE c.correo_cuenta = ? AND c.Habilitado = 1'
            );
            $stmt->execute([$usuario]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password'])) {
                if (new DateTime($row['licencia_expira']) < new DateTime()) {
                    session_regenerate_id(true);
                    $_SESSION['licencia_expirada'] = [
                        'empresa' => $row['nombre_empresa'],
                        'fecha'   => $row['licencia_expira'],
                    ];
                    header('Location: licencia_expirada.php');
                    exit;
                }
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['ultima_actividad'] = time();
                $_SESSION['huella'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                header('Location: ModoAnfitrion/vistaAnfitrion.php');
                exit;
            }
            $error = 'Usuario o contraseña incorrectos.';
        } catch (PDOException $e) {
            error_log('Error al iniciar sesión: ' . $e->getMessage());
            $error = 'No se pudo consultar la cuenta. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | OpenDoors</title>
    <link rel="icon" type="image/png" href="img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 14px;
            padding: 40px 36px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .35);
        }
        .logo {
            width: 64px; height: 64px;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: linear-gradient(135deg, #2c5364, #3a8fb7);
            display: flex; align-items: center; justify-content: center;
        }
        .logo svg { width: 34px; height: 34px; fill: #fff; }
        h1 { text-align: center; font-size: 1.4rem; color: #1b2a35; }
        .sub { text-align: center; color: #6b7a86; font-size: .9rem; margin: 6px 0 26px; }
        label { display: block; font-size: .85rem; font-weight: 600; color: #34444f; margin-bottom: 6px; }
        .field { margin-bottom: 18px; position: relative; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            font-size: .95rem;
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus { outline: none; border-color: #3a8fb7; box-shadow: 0 0 0 3px rgba(58, 143, 183, .2); }
        .toggle {
            position: absolute; right: 12px; top: 33px;
            background: none; border: none; cursor: pointer;
            color: #6b7a86; font-size: .8rem;
        }
        button.submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #2c5364, #3a8fb7);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
        }
        button.submit:hover { opacity: .92; }
        button.submit:active { transform: scale(.99); }
        .error {
            background: #fdecea; color: #b3261e;
            border: 1px solid #f5c2be;
            padding: 10px 12px; border-radius: 8px;
            font-size: .88rem; margin-bottom: 18px;
        }
        footer { text-align: center; color: #9aa7b0; font-size: .78rem; margin-top: 24px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="logo" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 21V9l9-6 9 6v12h-6v-7H9v7H3z"/></svg>
        </div>
        <h1>OpenDoors</h1>
        <p class="sub">Inicia sesión para continuar</p>

        <?php if ($error): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" value="<?= htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') ?>" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="toggle" onclick="var p=document.getElementById('password');var h=p.type==='password';p.type=h?'text':'password';this.textContent=h?'Ocultar':'Mostrar';">Mostrar</button>
            </div>
            <button type="submit" class="submit">Ingresar</button>
        </form>
        <footer>&copy; <?= date('Y') ?> OpenDoors</footer>
    </main>
</body>
</html>
