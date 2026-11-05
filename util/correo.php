<?php
// Envío de correo con la API HTTP de Resend (Render bloquea el puerto SMTP 587).
// Variables de entorno: RESEND_API_KEY (obligatoria) y RESEND_FROM (opcional).
// Sin RESEND_FROM se usa onboarding@resend.dev, que solo entrega al correo dueño de la cuenta de Resend;
// para enviar a cualquier destinatario hay que verificar un dominio en Resend y usar un remitente de ese dominio.

function smtpConfigurado(): bool
{
    return (string) getenv('RESEND_API_KEY') !== '' && function_exists('curl_init');
}

function enviarCorreo(string $para, string $asunto, string $texto): bool
{
    $apiKey = (string) getenv('RESEND_API_KEY');
    $desde = (string) (getenv('RESEND_FROM') ?: 'OpenDoors <onboarding@resend.dev>');

    // Evita inyección de cabeceras
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || $apiKey === '' || preg_match('/[\r\n]/', $asunto . $desde)) {
        error_log('Resend: falta RESEND_API_KEY o el destinatario no es válido.');
        return false;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode(
            ['from' => $desde, 'to' => [$para], 'subject' => $asunto, 'text' => $texto],
            JSON_UNESCAPED_UNICODE
        ),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $respuesta = curl_exec($ch);
    $codigoHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        error_log('Resend: error de conexión: ' . $errorCurl);
        return false;
    }
    if ($codigoHttp !== 200 && $codigoHttp !== 201) {
        error_log('Resend: HTTP ' . $codigoHttp . ' - ' . $respuesta);
        return false;
    }
    return true;
}
