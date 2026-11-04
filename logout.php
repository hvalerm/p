<?php
require_once __DIR__ . '/util/verificar.php';

// Solo por POST con token CSRF, para que otro sitio no pueda cerrar la sesión
iniciarSesionSegura();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    cerrarSesion();
}
header('Location: login.php');
exit;
