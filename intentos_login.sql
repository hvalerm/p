-- Control de intentos de login (protección contra fuerza bruta). Seguro de ejecutar más de una vez.
CREATE TABLE IF NOT EXISTS intentos_login (
  usuario varchar(255) NOT NULL PRIMARY KEY,
  intentos int NOT NULL DEFAULT 0,
  bloqueado_hasta datetime NULL DEFAULT NULL,
  ultimo_intento datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
