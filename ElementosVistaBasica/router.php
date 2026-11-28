<?php
// Solo se exponen las rutas necesarias para la vista previa local.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$permitidas = [
    '/' => __DIR__ . '/../index.php',
    '/index.html' => __DIR__ . '/../index.html',
    '/index.php' => __DIR__ . '/../index.php',
    '/ElementosVistaBasica/ModoAnfitrion/vistaAnfitrion.php' => __DIR__ . '/ModoAnfitrion/vistaAnfitrion.php',
    '/ElementosVistaBasica/Modulos/GESTALO/Nucleo/calendario.php' => __DIR__ . '/Modulos/GESTALO/Nucleo/calendario.php',
    '/ElementosVistaBasica/Modulos/GESTALO/Nucleo/calendario.js' => __DIR__ . '/Modulos/GESTALO/Nucleo/calendario.js',
    '/ElementosVistaBasica/Modulos/GESTALO/Nucleo/calendario.css' => __DIR__ . '/Modulos/GESTALO/Nucleo/calendario.css',
    '/ElementosVistaBasica/img/OpenDoors.png' => __DIR__ . '/img/OpenDoors.png',
];
if (!is_string($path) || !isset($permitidas[$path])) {
    http_response_code(404);
    exit('Recurso no disponible en la vista previa.');
}
$archivo = $permitidas[$path];
if (str_ends_with($archivo, '.php')) {
    require $archivo;
} else {
    $tipos = ['html' => 'text/html; charset=UTF-8', 'js' => 'text/javascript', 'css' => 'text/css', 'png' => 'image/png'];
    header('Content-Type: ' . $tipos[pathinfo($archivo, PATHINFO_EXTENSION)]);
    readfile($archivo);
}
