<?php
class CustomCryptographer {
    // Solo descifra: el cifrado lo hace el ESP32. Debe coincidir con su clave.
    // Se lee de la variable de entorno OPENDOORS_ENC_KEY.
    private static $targetLength = 1000;

    // Deriva la clave de 32 bytes en binario usando SHA-256
    private static function getKeyBytes(): string {
        $secret = getenv('OPENDOORS_ENC_KEY');
        if ($secret === false || $secret === '') {
            throw new RuntimeException('Falta la variable de entorno OPENDOORS_ENC_KEY.');
        }
        return hash('sha256', $secret, true);
    }

    public static function descifrarNumero(string $cadena1000): string {
        if (strlen($cadena1000) !== self::$targetLength) {
            throw new Exception("La cadena debe tener exactamente " . self::$targetLength . " caracteres.");
        }

        $fullPackage = ctype_xdigit($cadena1000) ? hex2bin($cadena1000) : false;
        if ($fullPackage === false) {
            throw new Exception("Fallo al descifrar los datos o clave incorrecta.");
        }
        $keyBytes = self::getKeyBytes();

        // Extraer IV (16 bytes)
        $iv = substr($fullPackage, 0, 16);

        // Extraer Longitud (2 bytes)
        $lengthUnpacked = unpack('v', substr($fullPackage, 16, 2));
        $cipherLength = $lengthUnpacked[1];
        if ($cipherLength < 16 || $cipherLength % 16 !== 0 || 18 + $cipherLength > strlen($fullPackage)) {
            throw new Exception("Fallo al descifrar los datos o clave incorrecta.");
        }

        // Extraer texto cifrado real
        $cipherBytes = substr($fullPackage, 18, $cipherLength);

        // Descifrar con AES-256-CBC
        $decrypted = openssl_decrypt(
            $cipherBytes, 
            'aes-256-cbc', 
            $keyBytes, 
            OPENSSL_RAW_DATA, 
            $iv
        );

        if ($decrypted === false) {
            throw new Exception("Fallo al descifrar los datos o clave incorrecta.");
        }

        if (!preg_match('/^[A-Za-z0-9]{1,400}$/', $decrypted)) {
            throw new Exception("Fallo al descifrar los datos o clave incorrecta.");
        }

        return $decrypted;
    }
}