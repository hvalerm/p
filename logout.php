<?php
require_once __DIR__ . '/util/Seguridad/verificar.php';
iniciarSesionSegura();
$csrf = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    http_response_code(403);
    exit('Solicitud no válida.');
}
cerrarSesion();
header('Location: /login.php');
exit;
