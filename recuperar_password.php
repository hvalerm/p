<?php
require_once __DIR__ . '/util/verificar.php';
require_once __DIR__ . '/util/db.php';
require_once __DIR__ . '/util/correo.php';
require_once __DIR__ . '/util/recuperar_comun.php';

iniciarSesionSegura();
if (haySesionActiva()) {
    recRedirigir('ModoAnfitrion/vistaAnfitrion.php');
}

$error = isset($_GET['vencido']) ? 'El código venció o ya no es válido. Solicita uno nuevo.' : '';
$correo = trim((string) ($_GET['correo'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim((string) ($_POST['correo'] ?? ''));

    if (!recCsrfValido()) {
        $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 50) {
        $error = 'Ingresa un correo válido.';
    } elseif (!smtpConfigurado()) {
        error_log('Recuperar contraseña: falta RESEND_API_KEY o la extensión curl de PHP.');
        $error = 'El envío de correos no está disponible por ahora. Contacta al administrador.';
    } elseif (time() - (int) ($_SESSION['rec_ultimo_envio'] ?? 0) < REC_ESPERA_SEGUNDOS) {
        $error = 'Espera un momento antes de solicitar otro código.';
    } else {
        try {
            $pdo = getPDO();
            $q = $pdo->prepare('SELECT id_cuenta FROM cuentas WHERE correo_cuenta = ? AND Habilitado = 1');
            $q->execute([$correo]);
            $idCuenta = $q->fetchColumn();

            if ($idCuenta !== false) {
                $q = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, ultimo_envio, NOW()) FROM recuperacion_password WHERE id_cuenta = ?');
                $q->execute([$idCuenta]);
                $desdeUltimo = $q->fetchColumn();

                // Si se pidió un código hace menos de un minuto no se envía otro (evita saturar el correo)
                if ($desdeUltimo === false || (int) $desdeUltimo >= REC_ESPERA_SEGUNDOS) {
                    $codigo = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
                    $pdo->prepare(
                        'INSERT INTO recuperacion_password (id_cuenta, codigo_hash, expira, intentos, verificado, ultimo_envio)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . REC_CODIGO_MINUTOS . ' MINUTE), 0, 0, NOW())
                         ON DUPLICATE KEY UPDATE codigo_hash = VALUES(codigo_hash), expira = VALUES(expira),
                             intentos = 0, verificado = 0, ultimo_envio = NOW()'
                    )->execute([$idCuenta, password_hash($codigo, PASSWORD_ARGON2ID)]);

                    $enviado = enviarCorreo(
                        $correo,
                        'Código para recuperar tu contraseña',
                        "Tu código de recuperación de OpenDoors es: $codigo\n\n"
                        . 'Es válido por ' . REC_CODIGO_MINUTOS . " minutos y solo puede usarse una vez.\n"
                        . "Si no lo solicitaste, ignora este mensaje: tu contraseña no cambiará.\n"
                    );
                    if (!$enviado) {
                        $pdo->prepare('DELETE FROM recuperacion_password WHERE id_cuenta = ?')->execute([$idCuenta]);
                        error_log('Recuperar contraseña: no se pudo enviar el correo a la cuenta ' . $idCuenta);
                    }
                }
            }

            // Misma respuesta exista o no el correo, para no revelar qué cuentas están registradas
            $_SESSION['rec_correo'] = $correo;
            $_SESSION['rec_ultimo_envio'] = time();
            recRedirigir('verificar_codigo.php');
        } catch (PDOException $e) {
            error_log('Error en recuperar_password: ' . $e->getMessage());
            $error = 'No se pudo procesar la solicitud. Inténtalo más tarde.';
        }
    }
}

recInicio('Recuperar contraseña', 'Ingresa tu correo registrado y te enviaremos un código de 10 dígitos.', $error);
?>
        <form method="post" action="recuperar_password.php" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= recEscapar(recCsrf()) ?>">
            <div class="field">
                <label for="correo">Correo registrado</label>
                <input type="email" id="correo" name="correo" value="<?= recEscapar($correo) ?>" maxlength="50" required autofocus autocomplete="email">
            </div>
            <button type="submit" class="submit">Enviar código</button>
        </form>
<?php recFin();
