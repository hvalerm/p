<?php
require_once __DIR__ . '/util/Seguridad/verificar.php';
require_once __DIR__ . '/util/Seguridad/intentos_login.php';

iniciarSesionSegura();
if (haySesionActiva()) {
    header('Location: /ModoAnfitrion/vistaAnfitrion.php');
    exit;
}
iniciarSesionSegura();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $csrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
    } elseif ($usuario === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        try {
            $pdo = getPDO();
            $clave = claveIntentos($usuario);
            $segundos = segundosBloqueado($pdo, $clave);
            if ($segundos > 0) {
                $error = 'Demasiados intentos fallidos. Inténtalo más tarde.';
            } else {
                $stmt = $pdo->prepare(
                    "SELECT idCuenta AS id, COALESCE(NULLIF(TRIM(nombreCuenta), ''), correoCuenta) AS nombre, pwd
                     FROM cuentas WHERE correoCuenta = ? AND habilitado = 1"
                );
                $stmt->execute([$usuario]);
                $cuenta = $stmt->fetch();
                if ($cuenta && password_verify($password, $cuenta['pwd'])) {
                    $empresa = empresaUnicaCuenta($pdo, (int) $cuenta['id']);
                    if (new DateTimeImmutable($empresa['licencia_expira']) < new DateTimeImmutable()) {
                        throw new DomainException('La licencia de la empresa ha expirado.');
                    }
                    limpiarIntentos($pdo, $clave);
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = (int) $cuenta['id'];
                    $_SESSION['empresa_id'] = (int) $empresa['id_empresa'];
                    $_SESSION['nombre'] = $cuenta['nombre'];
                    $_SESSION['ultima_actividad'] = time();
                    $_SESSION['huella'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    header('Location: /ModoAnfitrion/vistaAnfitrion.php');
                    exit;
                }
                registrarFallo($pdo, $clave);
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (DomainException $e) {
            error_log('Inicio de sesión rechazado: ' . $e->getMessage());
            $error = 'La cuenta o su empresa no tienen acceso. Contacta al administrador.';
        } catch (PDOException | RuntimeException $e) {
            error_log('No se pudo iniciar sesión: ' . $e->getMessage());
            http_response_code(503);
            $error = 'No se pudo iniciar sesión. Inténtalo más tarde.';
        }
    }
}
$h = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | OpenDoors</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); }
        main { width: 100%; max-width: 400px; padding: 32px; border-radius: 14px; background: white; }
        h1 { text-align: center; color: #1b2a35; }
        label { display: block; margin: 16px 0 6px; }
        input, button { width: 100%; padding: 12px; font: inherit; border-radius: 8px; }
        input { border: 1px solid #cfd8dc; }
        button { margin-top: 20px; border: 0; background: #2c5364; color: white; cursor: pointer; }
        .error { color: #b3261e; }
    </style>
</head>
<body>
    <main>
        <h1>OpenDoors</h1>
        <p>Inicia sesión para continuar.</p>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $h($error) ?></p><?php endif; ?>
        <form method="post" action="/login.php">
            <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" value="<?= $h($usuario) ?>" required autocomplete="username">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="submit">Ingresar</button>
        </form>
    </main>
</body>
</html>
