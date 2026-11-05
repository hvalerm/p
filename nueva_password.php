<?php
require_once __DIR__ . '/util/verificar.php';
require_once __DIR__ . '/util/db.php';
require_once __DIR__ . '/util/intentos_login.php';
require_once __DIR__ . '/util/recuperar_comun.php';

iniciarSesionSegura();
if (haySesionActiva()) {
    recRedirigir('ModoAnfitrion/vistaAnfitrion.php');
}
$idCuenta = (int) ($_SESSION['rec_cuenta'] ?? 0);
if ($idCuenta <= 0) {
    recRedirigir('recuperar_password.php');
}

$error = '';

try {
    $pdo = getPDO();
    // Solo se puede cambiar la contraseña con un código ya verificado y vigente
    $q = $pdo->prepare('SELECT 1 FROM recuperacion_password WHERE id_cuenta = ? AND verificado = 1 AND expira > NOW()');
    $q->execute([$idCuenta]);
    if (!$q->fetchColumn()) {
        unset($_SESSION['rec_cuenta']);
        recRedirigir('recuperar_password.php?vencido=1');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');

        if (!recCsrfValido()) {
            $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
        } elseif (!hash_equals($p1, $p2)) {
            $error = 'Las contraseñas no coinciden. Escríbelas de nuevo.';
        } elseif (mb_strlen($p1, 'UTF-8') < REC_PASSWORD_MIN) {
            $error = 'La contraseña debe tener al menos ' . REC_PASSWORD_MIN . ' caracteres.';
        } elseif (strlen($p1) > REC_PASSWORD_MAX) {
            $error = 'La contraseña es demasiado larga.';
        } else {
            $pdo->beginTransaction();
            // Consumir el código y cambiar la contraseña van juntos; el código solo sirve una vez
            $d = $pdo->prepare('DELETE FROM recuperacion_password WHERE id_cuenta = ? AND verificado = 1 AND expira > NOW()');
            $d->execute([$idCuenta]);
            if ($d->rowCount() !== 1) {
                $pdo->rollBack();
                unset($_SESSION['rec_cuenta']);
                recRedirigir('recuperar_password.php?vencido=1');
            }
            $pdo->prepare('UPDATE cuentas SET pwd = ? WHERE id_cuenta = ?')
                ->execute([password_hash($p1, PASSWORD_ARGON2ID), $idCuenta]);
            $pdo->commit();

            // También se libera un posible bloqueo por intentos de login
            try {
                limpiarIntentos($pdo, claveIntentos((string) ($_SESSION['rec_correo'] ?? '')));
            } catch (PDOException $e) {
                error_log('No se pudo reiniciar los intentos de login: ' . $e->getMessage());
            }
            unset($_SESSION['rec_cuenta'], $_SESSION['rec_correo'], $_SESSION['rec_ultimo_envio']);
            session_regenerate_id(true);
            recRedirigir('login.php?cambiada=1');
        }
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en nueva_password: ' . $e->getMessage());
    $error = 'No se pudo cambiar la contraseña. Inténtalo más tarde.';
}

recInicio('Nueva contraseña', 'Escribe tu nueva contraseña dos veces para confirmarla.', $error);
?>
        <form method="post" action="nueva_password.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= recEscapar(recCsrf()) ?>">
            <div class="field">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" minlength="<?= REC_PASSWORD_MIN ?>" required autofocus autocomplete="new-password">
                <p class="ayuda">Mínimo <?= REC_PASSWORD_MIN ?> caracteres.</p>
            </div>
            <div class="field">
                <label for="password2">Repite la contraseña</label>
                <input type="password" id="password2" name="password2" minlength="<?= REC_PASSWORD_MIN ?>" required autocomplete="new-password">
            </div>
            <button type="submit" class="submit">Cambiar contraseña</button>
        </form>
<?php recFin();
