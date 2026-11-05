-- Códigos de recuperación de contraseña (10 dígitos, 5 minutos). Seguro de ejecutar más de una vez.
CREATE TABLE IF NOT EXISTS recuperacion_password (
  id_cuenta int NOT NULL PRIMARY KEY,
  codigo_hash varchar(255) NOT NULL,
  expira datetime NOT NULL,
  intentos int NOT NULL DEFAULT 0,
  verificado tinyint(1) NOT NULL DEFAULT 0,
  ultimo_envio datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_cuenta) REFERENCES cuentas (id_cuenta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
