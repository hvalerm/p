<?php
// Cabeceras de seguridad comunes. Se carga desde util/db.php, que usan todas las páginas.

function esHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function enviarCabecerasSeguridad(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    // El panel se muestra dentro de un iframe del mismo sitio
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (esHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

enviarCabecerasSeguridad();
