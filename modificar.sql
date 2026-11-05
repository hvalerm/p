-- Eliminar triggers de las tablas de logs
DROP TRIGGER IF EXISTS trg_after_insert_logs_normales_general_microcontroladores;
DROP TRIGGER IF EXISTS trg_after_insert_no_permitida_microcontroladores;

-- Eliminar triggers de microcontroladores
DROP TRIGGER IF EXISTS trg_after_insert_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_delete_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_id_local_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_ultimaConsulta_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_ultimaEjecucion_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_ultimaApertura_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_cantidadAperturas_microcontrolador;
DROP TRIGGER IF EXISTS trg_after_update_habilitado_microcontrolador;
DROP TRIGGER IF EXISTS trg_before_update_no_permitida_id_microcontrolador_micros;
DROP TRIGGER IF EXISTS trg_after_update_no_permitida_id_microcontrolador_micros;
DROP TRIGGER IF EXISTS trg_before_update_no_permitida_id_empresa_micros;
DROP TRIGGER IF EXISTS trg_after_update_no_permitida_id_empresa_micros;

-- Eliminar tablas
DROP TABLE IF EXISTS microcontroladores;
DROP TABLE IF EXISTS logs_microcontroladores;
DROP TABLE IF EXISTS logs_acciones_no_permitidas_microcontroladores;

-- Recrear tablas (con ultimaConsulta en lugar de ultimaEjecucion)
CREATE TABLE microcontroladores (
  id_microcontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_local int NOT NULL,
  id_empresa int NOT NULL,
  ultimaConsulta datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimaApertura datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cantidadAperturas int NOT NULL DEFAULT '0',
  habilitado tinyint(1) NOT NULL DEFAULT '1',
  KEY id_local (id_local,id_empresa),
  FOREIGN KEY (id_local, id_empresa) REFERENCES locales (id_local, id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_microcontroladores (
  id_log_microcontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_microcontrolador int NOT NULL,
  id_local int NOT NULL,
  id_empresa int NOT NULL,
  descripcion_log_microcontrolador text NOT NULL,
  fecha_registro_log_microcontrolador datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_microcontroladores (
  id_log_microcontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_empresa int NOT NULL,
  id_microcontrolador int NOT NULL,
  descripcion_log text NOT NULL,
  fecha_registro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recrear triggers
DELIMITER $$

-- --------------- LOGS A TABLAS GENERALES: MICROCONTROLADORES ---------------
CREATE TRIGGER trg_after_insert_logs_normales_general_microcontroladores
AFTER INSERT ON logs_microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tabla_afectada,
        id_registro,
        id_empresa,
        tipo_accion,
        descripcion_log
    )
    VALUES (
        'Microcontroladores',
        NEW.id_microcontrolador,
        NEW.id_empresa,
        CASE
            WHEN NEW.descripcion_log_microcontrolador LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcion_log_microcontrolador LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcion_log_microcontrolador LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcion_log_microcontrolador
    );
END$$

CREATE TRIGGER trg_after_insert_no_permitida_microcontroladores
AFTER INSERT ON logs_acciones_no_permitidas_microcontroladores
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tabla_afectada,
    id_empresa,
    id_registro,
    tipo_accion,
    descripcion_log,
    fecha_registro
)
VALUES (
    'Microcontroladores',
    NEW.id_empresa,
    NEW.id_microcontrolador,
    'ACCION_NO_PERMITIDA',
    NEW.descripcion_log,
    NEW.fecha_registro
)$$

-- --------------- MICROCONTROLADORES ---------------
CREATE TRIGGER trg_after_insert_microcontrolador
AFTER INSERT ON microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
    VALUES (
        NEW.id_microcontrolador,
        NEW.id_local,
        NEW.id_empresa,
        CONCAT('[INSERT] Se creó un nuevo microcontrolador con ID: ', NEW.id_microcontrolador)
    );
END$$

CREATE TRIGGER trg_after_delete_microcontrolador
AFTER DELETE ON microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
    VALUES (
        OLD.id_microcontrolador,
        OLD.id_local,
        OLD.id_empresa,
        CONCAT('[DELETE] Se eliminó el registro del microcontrolador ID: ', OLD.id_microcontrolador)
    );
END$$

CREATE TRIGGER trg_after_update_id_local_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.id_local <> NEW.id_local THEN
        INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
        VALUES (
            NEW.id_microcontrolador,
            NEW.id_local,
            NEW.id_empresa,
            CONCAT(
                '[UPDATE] Modificación en el local del microcontrolador. Anterior: ',
                OLD.id_local,
                ' | Nuevo: ',
                NEW.id_local
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_ultimaConsulta_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.ultimaConsulta <> NEW.ultimaConsulta THEN
        INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
        VALUES (
            NEW.id_microcontrolador,
            NEW.id_local,
            NEW.id_empresa,
            CONCAT(
                '[UPDATE] Modificación en ultimaConsulta. Anterior: ',
                OLD.ultimaConsulta,
                ' | Nuevo: ',
                NEW.ultimaConsulta
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_ultimaApertura_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.ultimaApertura <> NEW.ultimaApertura THEN
        INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
        VALUES (
            NEW.id_microcontrolador,
            NEW.id_local,
            NEW.id_empresa,
            CONCAT(
                '[UPDATE] Modificación en ultimaApertura. Anterior: ',
                OLD.ultimaApertura,
                ' | Nuevo: ',
                NEW.ultimaApertura
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_cantidadAperturas_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.cantidadAperturas <> NEW.cantidadAperturas THEN
        INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
        VALUES (
            NEW.id_microcontrolador,
            NEW.id_local,
            NEW.id_empresa,
            CONCAT(
                '[UPDATE] Modificación en cantidadAperturas. Anterior: ',
                OLD.cantidadAperturas,
                ' | Nuevo: ',
                NEW.cantidadAperturas
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_habilitado_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_microcontroladores (id_microcontrolador, id_local, id_empresa, descripcion_log_microcontrolador)
        VALUES (
            NEW.id_microcontrolador,
            NEW.id_local,
            NEW.id_empresa,
            CONCAT(
                '[UPDATE] Modificación en estado Habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        );
    END IF;
END$$

-- --------------- ACCIONES NO PERMITIDAS: MICROCONTROLADORES ---------------
CREATE TRIGGER trg_before_update_no_permitida_id_microcontrolador_micros
BEFORE UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.id_microcontrolador <> NEW.id_microcontrolador THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (id_empresa, id_microcontrolador, descripcion_log)
        VALUES (
            OLD.id_empresa,
            OLD.id_microcontrolador,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de id_microcontrolador. Original: ',
                OLD.id_microcontrolador,
                ' | Intentado: ',
                NEW.id_microcontrolador
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo id_microcontrolador.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_microcontrolador_micros
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.id_microcontrolador <> NEW.id_microcontrolador THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (id_empresa, id_microcontrolador, descripcion_log)
        VALUES (
            OLD.id_empresa,
            OLD.id_microcontrolador,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en id_microcontrolador. Original: ',
                OLD.id_microcontrolador,
                ' | Modificado: ',
                NEW.id_microcontrolador
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_no_permitida_id_empresa_micros
BEFORE UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.id_empresa <> NEW.id_empresa THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (id_empresa, id_microcontrolador, descripcion_log)
        VALUES (
            OLD.id_empresa,
            OLD.id_microcontrolador,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de id_empresa. Original: ',
                OLD.id_empresa,
                ' | Intentado: ',
                NEW.id_empresa
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo id_empresa.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_empresa_micros
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.id_empresa <> NEW.id_empresa THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (id_empresa, id_microcontrolador, descripcion_log)
        VALUES (
            OLD.id_empresa,
            OLD.id_microcontrolador,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en id_empresa. Original: ',
                OLD.id_empresa,
                ' | Modificado: ',
                NEW.id_empresa
            )
        );
    END IF;
END$$

DELIMITER ;
