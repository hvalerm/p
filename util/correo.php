<?php
// Envío de correo por SMTP sin librerías externas.
// Variables de entorno: SMTP_HOST, SMTP_PORT (587 por defecto), SMTP_USER, SMTP_PASS,
// SMTP_FROM (por defecto SMTP_USER), SMTP_SECURE = tls (STARTTLS, por defecto) | ssl | none

function smtpConfigurado(): bool
{
    return (string) getenv('SMTP_HOST') !== '' && (string) getenv('SMTP_USER') !== '';
}

function smtpLeer($fp): array
{
    $codigo = 0;
    $texto = '';
    while (($linea = fgets($fp, 1024)) !== false) {
        $texto .= $linea;
        $codigo = (int) substr($linea, 0, 3);
        if (strlen($linea) < 4 || $linea[3] === ' ') {
            break;
        }
    }
    return [$codigo, $texto];
}

function smtpComando($fp, string $cmd, array $esperados): bool
{
    global $smtpUltimaRespuesta;
    if ($cmd !== '') {
        fwrite($fp, $cmd . "\r\n");
    }
    [$codigo, $texto] = smtpLeer($fp);
    $smtpUltimaRespuesta = trim($texto);
    return in_array($codigo, $esperados, true);
}

function enviarCorreo(string $para, string $asunto, string $texto): bool
{
    $host = (string) getenv('SMTP_HOST');
    $usuario = (string) getenv('SMTP_USER');
    // Gmail muestra la contraseña de aplicación en grupos con espacios; no forman parte de la clave
    $clave = preg_replace('/\s+/', '', (string) getenv('SMTP_PASS'));
    $desde = (string) (getenv('SMTP_FROM') ?: $usuario);
    $seguridad = strtolower((string) (getenv('SMTP_SECURE') ?: 'tls'));
    $puerto = (int) (getenv('SMTP_PORT') ?: ($seguridad === 'ssl' ? 465 : 587));

    // Evita inyección de cabeceras
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || !filter_var($desde, FILTER_VALIDATE_EMAIL) || $host === '') {
        return false;
    }

    $fp = @stream_socket_client(($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto, $errno, $errstr, 10);
    if (!$fp) {
        error_log('SMTP: no se pudo conectar: ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 15);

    try {
        if (!smtpComando($fp, '', [220])) {
            throw new RuntimeException('saludo inicial');
        }
        if (!smtpComando($fp, 'EHLO opendoors', [250])) {
            throw new RuntimeException('EHLO');
        }
        if ($seguridad === 'tls') {
            if (!smtpComando($fp, 'STARTTLS', [220]) || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS');
            }
            if (!smtpComando($fp, 'EHLO opendoors', [250])) {
                throw new RuntimeException('EHLO tras STARTTLS');
            }
        }
        if ($usuario !== '') {
            if (!smtpComando($fp, 'AUTH LOGIN', [334])
                || !smtpComando($fp, base64_encode($usuario), [334])
                || !smtpComando($fp, base64_encode($clave), [235])) {
                throw new RuntimeException('autenticación');
            }
        }
        if (!smtpComando($fp, 'MAIL FROM:<' . $desde . '>', [250])
            || !smtpComando($fp, 'RCPT TO:<' . $para . '>', [250, 251])
            || !smtpComando($fp, 'DATA', [354])) {
            throw new RuntimeException('remitente/destinatario');
        }

        $cabeceras = [
            'From: OpenDoors <' . $desde . '>',
            'To: <' . $para . '>',
            'Subject: =?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $asunto)) . '?=',
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n" . chunk_split(base64_encode($texto), 76, "\r\n");
        // El cuerpo va en base64, por lo que nunca hay una línea que sea solo "."
        if (!smtpComando($fp, rtrim($mensaje, "\r\n") . "\r\n.", [250])) {
            throw new RuntimeException('envío del mensaje');
        }
        smtpComando($fp, 'QUIT', [221]);
        return true;
    } catch (RuntimeException $e) {
        global $smtpUltimaRespuesta;
        error_log('SMTP: falló en ' . $e->getMessage() . ' (respuesta del servidor: ' . ($smtpUltimaRespuesta ?? 'ninguna') . ')');
        return false;
    } finally {
        fclose($fp);
    }
}
