-- GESTALO: esquema inicial para MySQL 8.4.
-- Instalacion nueva: ejecutar empresas.sql, opendoors.sql y gestalo.sql, en
-- ese orden y en el mismo servidor MySQL. No insertar empresas hasta terminar.
-- Crear y seleccionar la base gestalo antes de ejecutar este archivo.
-- Ejecutar solo en una base gestalo vacia, tras autorizacion y respaldo.
-- No es una migracion de una instalacion anterior.
-- Cada conexion de aplicacion debe fijar time_zone = '+00:00'.
-- La cuenta instaladora necesita REFERENCES sobre empresas.empresas y EVENT.
-- La base empresas debe estar en el mismo servidor y tener idEmpresa INT PRIMARY KEY.
-- El borrado fisico es excepcional y queda registrado en empresas.logs_empresas.
-- La referencia se borra en cascada, salvo que existan datos dependientes.
-- Las cascadas no ejecutan el trigger de borrado de empresas en Gestalo.
-- No conceder escritura directa en gestalo.empresas a la aplicacion.
-- Para instalaciones existentes consultar actualizacion.sql.
-- Los controles de empresa, autorizacion, existencias y solapamientos tambien
-- requieren validacion transaccional en la aplicacion; este archivo no la implementa.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE empresas (
    idEmpresa INT NOT NULL,
    PRIMARY KEY (idEmpresa),
    CONSTRAINT fk_gestalo_empresa_principal FOREIGN KEY (idEmpresa)
        REFERENCES empresas.empresas (idEmpresa)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE inmuebles (
    idInmueble INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    nombreInmueble VARCHAR(100) NOT NULL,
    direccionInmueble VARCHAR(255) NOT NULL,
    zonaHoraria VARCHAR(64) NOT NULL DEFAULT 'America/Lima',
    habilitado BOOLEAN NOT NULL DEFAULT 1,
    eliminado BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (idInmueble),
    UNIQUE KEY uq_inmuebles_empresa_id (idEmpresa, idInmueble),
    CONSTRAINT fk_inmuebles_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE alojamientos (
    idAlojamiento INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    idInmueble INT NOT NULL,
    nombreAlojamiento VARCHAR(100) NOT NULL,
    disponible BOOLEAN NOT NULL DEFAULT 1,
    habilitado BOOLEAN NOT NULL DEFAULT 1,
    eliminado BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (idAlojamiento),
    UNIQUE KEY uq_alojamientos_empresa_inmueble_id (idEmpresa, idInmueble, idAlojamiento),
    UNIQUE KEY uq_alojamientos_empresa_id (idEmpresa, idAlojamiento),
    CONSTRAINT fk_alojamientos_inmuebles FOREIGN KEY (idEmpresa, idInmueble)
        REFERENCES inmuebles (idEmpresa, idInmueble) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE huespedes (
    idHuesped INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    tipoDocumento VARCHAR(30) NOT NULL,
    numeroDocumento VARCHAR(30) NOT NULL,
    paisEmisorDocumento CHAR(2) NOT NULL,
    fechaNacimiento DATE NULL,
    nacionalidad CHAR(2) NULL,
    telefono VARCHAR(30) NULL,
    correo VARCHAR(254) NULL,
    direccion VARCHAR(255) NULL,
    vetado BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (idHuesped),
    UNIQUE KEY uq_huespedes_empresa_id (idEmpresa, idHuesped),
    CONSTRAINT fk_huespedes_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE personal (
    idPersonal INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    tipoDocumento VARCHAR(30) NOT NULL,
    numeroDocumento VARCHAR(30) NOT NULL,
    paisEmisorDocumento CHAR(2) NOT NULL,
    fechaNacimiento DATE NULL,
    nacionalidad CHAR(2) NULL,
    telefono VARCHAR(30) NULL,
    correo VARCHAR(254) NULL,
    direccion VARCHAR(255) NULL,
    eliminado BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY (idPersonal),
    UNIQUE KEY uq_personal_empresa_id (idEmpresa, idPersonal),
    CONSTRAINT fk_personal_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE personal_tarea (
    idTarea INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    idPersonal INT NOT NULL,
    idAlojamiento INT NOT NULL,
    fechaYHora DATETIME NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    estado ENUM('pendiente', 'en proceso', 'completada') NOT NULL DEFAULT 'pendiente',
    PRIMARY KEY (idTarea),
    KEY idx_tareas_personal (idEmpresa, idPersonal),
    KEY idx_tareas_alojamiento (idEmpresa, idAlojamiento),
    CONSTRAINT fk_tareas_personal FOREIGN KEY (idEmpresa, idPersonal)
        REFERENCES personal (idEmpresa, idPersonal) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_tareas_alojamientos FOREIGN KEY (idEmpresa, idAlojamiento)
        REFERENCES alojamientos (idEmpresa, idAlojamiento) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE plataformas (
    idPlataforma INT NOT NULL AUTO_INCREMENT,
    nombrePlataforma VARCHAR(50) NOT NULL,
    PRIMARY KEY (idPlataforma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE empresas_plataformas (
    idEmpresa INT NOT NULL,
    idPlataforma INT NOT NULL,
    habilitado BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY (idEmpresa, idPlataforma),
    CONSTRAINT fk_empresas_plataformas_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_empresas_plataformas_plataformas FOREIGN KEY (idPlataforma)
        REFERENCES plataformas (idPlataforma) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE estancias (
    idEstancia INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    idAlojamiento INT NOT NULL,
    idHuespedTitular INT NOT NULL,
    estado ENUM('reservada', 'en curso', 'finalizada', 'cancelada') NOT NULL DEFAULT 'reservada',
    entradaPrevista DATETIME NULL,
    salidaPrevista DATETIME NULL,
    checkIn DATETIME NULL,
    checkOut DATETIME NULL,
    PRIMARY KEY (idEstancia),
    UNIQUE KEY uq_estancias_empresa_id (idEmpresa, idEstancia),
    KEY idx_estancias_ocupacion (idEmpresa, idAlojamiento, estado, entradaPrevista, salidaPrevista),
    KEY idx_estancias_entrada (idEmpresa, entradaPrevista),
    KEY idx_estancias_salida (idEmpresa, salidaPrevista),
    CONSTRAINT fk_estancias_alojamientos FOREIGN KEY (idEmpresa, idAlojamiento)
        REFERENCES alojamientos (idEmpresa, idAlojamiento) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_estancias_huespedes FOREIGN KEY (idEmpresa, idHuespedTitular)
        REFERENCES huespedes (idEmpresa, idHuesped) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_estancias_previstas CHECK (
        entradaPrevista IS NULL OR salidaPrevista IS NULL OR salidaPrevista > entradaPrevista
    ),
    CONSTRAINT chk_estancias_reales CHECK (
        checkOut IS NULL OR (checkIn IS NOT NULL AND checkOut > checkIn)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE estancias_acompanantes (
    idEmpresa INT NOT NULL,
    idEstancia INT NOT NULL,
    idHuesped INT NOT NULL,
    PRIMARY KEY (idEmpresa, idEstancia, idHuesped),
    CONSTRAINT fk_acompanantes_estancias FOREIGN KEY (idEmpresa, idEstancia)
        REFERENCES estancias (idEmpresa, idEstancia) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_acompanantes_huespedes FOREIGN KEY (idEmpresa, idHuesped)
        REFERENCES huespedes (idEmpresa, idHuesped) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE productos (
    idProducto INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    nombreProducto VARCHAR(100) NOT NULL,
    unidad VARCHAR(30) NOT NULL,
    habilitado BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY (idProducto),
    UNIQUE KEY uq_productos_empresa_id (idEmpresa, idProducto),
    CONSTRAINT fk_productos_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Una ubicacion de inventario representa la empresa, un inmueble o un alojamiento.
CREATE TABLE almacenes (
    idAlmacen INT NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    tipo ENUM('empresa', 'inmueble', 'alojamiento') NOT NULL,
    idInmueble INT NULL,
    idAlojamiento INT NULL,
    empresaPrincipal INT GENERATED ALWAYS AS
        (CASE WHEN tipo = 'empresa' THEN idEmpresa ELSE NULL END) STORED,
    inmueblePrincipal INT GENERATED ALWAYS AS
        (CASE WHEN tipo = 'inmueble' THEN idInmueble ELSE NULL END) STORED,
    PRIMARY KEY (idAlmacen),
    UNIQUE KEY uq_almacenes_empresa_id (idEmpresa, idAlmacen),
    UNIQUE KEY uq_almacen_empresa_principal (empresaPrincipal),
    UNIQUE KEY uq_almacen_inmueble (idEmpresa, inmueblePrincipal),
    UNIQUE KEY uq_almacen_alojamiento (idEmpresa, idAlojamiento),
    CONSTRAINT fk_almacenes_empresas FOREIGN KEY (idEmpresa)
        REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_almacenes_inmuebles FOREIGN KEY (idEmpresa, idInmueble)
        REFERENCES inmuebles (idEmpresa, idInmueble) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_almacenes_alojamientos FOREIGN KEY (idEmpresa, idInmueble, idAlojamiento)
        REFERENCES alojamientos (idEmpresa, idInmueble, idAlojamiento) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_almacenes_tipo CHECK (
        (tipo = 'empresa' AND idInmueble IS NULL AND idAlojamiento IS NULL)
        OR (tipo = 'inmueble' AND idInmueble IS NOT NULL AND idAlojamiento IS NULL)
        OR (tipo = 'alojamiento' AND idInmueble IS NOT NULL AND idAlojamiento IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Cantidades siempre positivas; el tipo determina su efecto.
-- Registrar una transferencia en una sola fila permite restar del origen y
-- sumar al destino atomicamente. El kardex no registra precios ni moneda.
CREATE TABLE kardex_movimientos (
    idMovimiento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idEmpresa INT NOT NULL,
    idProducto INT NOT NULL,
    tipo ENUM('entrada', 'salida', 'traslado') NOT NULL,
    idAlmacenOrigen INT NULL,
    idAlmacenDestino INT NULL,
    cantidad DECIMAL(16,3) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    fecha DATETIME(6) NOT NULL,
    PRIMARY KEY (idMovimiento),
    KEY idx_kardex_producto_fecha (idEmpresa, idProducto, fecha, idMovimiento),
    KEY idx_kardex_origen (idEmpresa, idAlmacenOrigen, idProducto),
    KEY idx_kardex_destino (idEmpresa, idAlmacenDestino, idProducto),
    CONSTRAINT fk_kardex_productos FOREIGN KEY (idEmpresa, idProducto)
        REFERENCES productos (idEmpresa, idProducto) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_kardex_origen FOREIGN KEY (idEmpresa, idAlmacenOrigen)
        REFERENCES almacenes (idEmpresa, idAlmacen) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_kardex_destino FOREIGN KEY (idEmpresa, idAlmacenDestino)
        REFERENCES almacenes (idEmpresa, idAlmacen) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_kardex_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_kardex_tipo CHECK (
        (tipo = 'entrada' AND idAlmacenOrigen IS NULL AND idAlmacenDestino IS NOT NULL)
        OR (tipo = 'salida' AND idAlmacenOrigen IS NOT NULL AND idAlmacenDestino IS NULL)
        OR (tipo = 'traslado' AND idAlmacenOrigen IS NOT NULL
            AND idAlmacenDestino IS NOT NULL AND idAlmacenOrigen <> idAlmacenDestino)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Los datos personales completos se conservan en el historial por seis meses.
-- La cuenta que consulte esta tabla requiere autorizacion y aislamiento por empresa.
CREATE TABLE logs_gestalo (
    idLog BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idEmpresa INT NULL,
    entidad VARCHAR(40) NOT NULL,
    operacion ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    fecha DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    datosAnteriores JSON NULL,
    datosNuevos JSON NULL,
    PRIMARY KEY (idLog),
    KEY idx_logs_fecha (fecha),
    KEY idx_logs_empresa_fecha (idEmpresa, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Auditoria: cada operacion guarda sus valores completos.
CREATE TRIGGER trg_empresas_insert
AFTER INSERT ON empresas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'empresas', 'INSERT', NULL, JSON_OBJECT('idEmpresa', NEW.idEmpresa));

CREATE TRIGGER trg_empresas_update
AFTER UPDATE ON empresas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'empresas', 'UPDATE', JSON_OBJECT('idEmpresa', OLD.idEmpresa), JSON_OBJECT('idEmpresa', NEW.idEmpresa));

CREATE TRIGGER trg_empresas_delete
AFTER DELETE ON empresas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'empresas', 'DELETE', JSON_OBJECT('idEmpresa', OLD.idEmpresa), NULL);

CREATE TRIGGER trg_inmuebles_insert
AFTER INSERT ON inmuebles FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'inmuebles', 'INSERT', NULL, JSON_OBJECT('idInmueble', NEW.idInmueble, 'idEmpresa', NEW.idEmpresa, 'nombreInmueble', NEW.nombreInmueble, 'direccionInmueble', NEW.direccionInmueble, 'zonaHoraria', NEW.zonaHoraria, 'habilitado', NEW.habilitado, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_inmuebles_update
AFTER UPDATE ON inmuebles FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'inmuebles', 'UPDATE', JSON_OBJECT('idInmueble', OLD.idInmueble, 'idEmpresa', OLD.idEmpresa, 'nombreInmueble', OLD.nombreInmueble, 'direccionInmueble', OLD.direccionInmueble, 'zonaHoraria', OLD.zonaHoraria, 'habilitado', OLD.habilitado, 'eliminado', OLD.eliminado), JSON_OBJECT('idInmueble', NEW.idInmueble, 'idEmpresa', NEW.idEmpresa, 'nombreInmueble', NEW.nombreInmueble, 'direccionInmueble', NEW.direccionInmueble, 'zonaHoraria', NEW.zonaHoraria, 'habilitado', NEW.habilitado, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_inmuebles_delete
AFTER DELETE ON inmuebles FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'inmuebles', 'DELETE', JSON_OBJECT('idInmueble', OLD.idInmueble, 'idEmpresa', OLD.idEmpresa, 'nombreInmueble', OLD.nombreInmueble, 'direccionInmueble', OLD.direccionInmueble, 'zonaHoraria', OLD.zonaHoraria, 'habilitado', OLD.habilitado, 'eliminado', OLD.eliminado), NULL);

CREATE TRIGGER trg_alojamientos_insert
AFTER INSERT ON alojamientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'alojamientos', 'INSERT', NULL, JSON_OBJECT('idAlojamiento', NEW.idAlojamiento, 'idEmpresa', NEW.idEmpresa, 'idInmueble', NEW.idInmueble, 'nombreAlojamiento', NEW.nombreAlojamiento, 'disponible', NEW.disponible, 'habilitado', NEW.habilitado, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_alojamientos_update
AFTER UPDATE ON alojamientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'alojamientos', 'UPDATE', JSON_OBJECT('idAlojamiento', OLD.idAlojamiento, 'idEmpresa', OLD.idEmpresa, 'idInmueble', OLD.idInmueble, 'nombreAlojamiento', OLD.nombreAlojamiento, 'disponible', OLD.disponible, 'habilitado', OLD.habilitado, 'eliminado', OLD.eliminado), JSON_OBJECT('idAlojamiento', NEW.idAlojamiento, 'idEmpresa', NEW.idEmpresa, 'idInmueble', NEW.idInmueble, 'nombreAlojamiento', NEW.nombreAlojamiento, 'disponible', NEW.disponible, 'habilitado', NEW.habilitado, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_alojamientos_delete
AFTER DELETE ON alojamientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'alojamientos', 'DELETE', JSON_OBJECT('idAlojamiento', OLD.idAlojamiento, 'idEmpresa', OLD.idEmpresa, 'idInmueble', OLD.idInmueble, 'nombreAlojamiento', OLD.nombreAlojamiento, 'disponible', OLD.disponible, 'habilitado', OLD.habilitado, 'eliminado', OLD.eliminado), NULL);

CREATE TRIGGER trg_huespedes_insert
AFTER INSERT ON huespedes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'huespedes', 'INSERT', NULL, JSON_OBJECT('idHuesped', NEW.idHuesped, 'idEmpresa', NEW.idEmpresa, 'nombres', NEW.nombres, 'apellidos', NEW.apellidos, 'tipoDocumento', NEW.tipoDocumento, 'numeroDocumento', NEW.numeroDocumento, 'paisEmisorDocumento', NEW.paisEmisorDocumento, 'fechaNacimiento', NEW.fechaNacimiento, 'nacionalidad', NEW.nacionalidad, 'telefono', NEW.telefono, 'correo', NEW.correo, 'direccion', NEW.direccion, 'vetado', NEW.vetado));

CREATE TRIGGER trg_huespedes_update
AFTER UPDATE ON huespedes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'huespedes', 'UPDATE', JSON_OBJECT('idHuesped', OLD.idHuesped, 'idEmpresa', OLD.idEmpresa, 'nombres', OLD.nombres, 'apellidos', OLD.apellidos, 'tipoDocumento', OLD.tipoDocumento, 'numeroDocumento', OLD.numeroDocumento, 'paisEmisorDocumento', OLD.paisEmisorDocumento, 'fechaNacimiento', OLD.fechaNacimiento, 'nacionalidad', OLD.nacionalidad, 'telefono', OLD.telefono, 'correo', OLD.correo, 'direccion', OLD.direccion, 'vetado', OLD.vetado), JSON_OBJECT('idHuesped', NEW.idHuesped, 'idEmpresa', NEW.idEmpresa, 'nombres', NEW.nombres, 'apellidos', NEW.apellidos, 'tipoDocumento', NEW.tipoDocumento, 'numeroDocumento', NEW.numeroDocumento, 'paisEmisorDocumento', NEW.paisEmisorDocumento, 'fechaNacimiento', NEW.fechaNacimiento, 'nacionalidad', NEW.nacionalidad, 'telefono', NEW.telefono, 'correo', NEW.correo, 'direccion', NEW.direccion, 'vetado', NEW.vetado));

CREATE TRIGGER trg_huespedes_delete
AFTER DELETE ON huespedes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'huespedes', 'DELETE', JSON_OBJECT('idHuesped', OLD.idHuesped, 'idEmpresa', OLD.idEmpresa, 'nombres', OLD.nombres, 'apellidos', OLD.apellidos, 'tipoDocumento', OLD.tipoDocumento, 'numeroDocumento', OLD.numeroDocumento, 'paisEmisorDocumento', OLD.paisEmisorDocumento, 'fechaNacimiento', OLD.fechaNacimiento, 'nacionalidad', OLD.nacionalidad, 'telefono', OLD.telefono, 'correo', OLD.correo, 'direccion', OLD.direccion, 'vetado', OLD.vetado), NULL);

CREATE TRIGGER trg_personal_insert
AFTER INSERT ON personal FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'personal', 'INSERT', NULL, JSON_OBJECT('idPersonal', NEW.idPersonal, 'idEmpresa', NEW.idEmpresa, 'nombres', NEW.nombres, 'apellidos', NEW.apellidos, 'tipoDocumento', NEW.tipoDocumento, 'numeroDocumento', NEW.numeroDocumento, 'paisEmisorDocumento', NEW.paisEmisorDocumento, 'fechaNacimiento', NEW.fechaNacimiento, 'nacionalidad', NEW.nacionalidad, 'telefono', NEW.telefono, 'correo', NEW.correo, 'direccion', NEW.direccion, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_personal_update
AFTER UPDATE ON personal FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'personal', 'UPDATE', JSON_OBJECT('idPersonal', OLD.idPersonal, 'idEmpresa', OLD.idEmpresa, 'nombres', OLD.nombres, 'apellidos', OLD.apellidos, 'tipoDocumento', OLD.tipoDocumento, 'numeroDocumento', OLD.numeroDocumento, 'paisEmisorDocumento', OLD.paisEmisorDocumento, 'fechaNacimiento', OLD.fechaNacimiento, 'nacionalidad', OLD.nacionalidad, 'telefono', OLD.telefono, 'correo', OLD.correo, 'direccion', OLD.direccion, 'eliminado', OLD.eliminado), JSON_OBJECT('idPersonal', NEW.idPersonal, 'idEmpresa', NEW.idEmpresa, 'nombres', NEW.nombres, 'apellidos', NEW.apellidos, 'tipoDocumento', NEW.tipoDocumento, 'numeroDocumento', NEW.numeroDocumento, 'paisEmisorDocumento', NEW.paisEmisorDocumento, 'fechaNacimiento', NEW.fechaNacimiento, 'nacionalidad', NEW.nacionalidad, 'telefono', NEW.telefono, 'correo', NEW.correo, 'direccion', NEW.direccion, 'eliminado', NEW.eliminado));

CREATE TRIGGER trg_personal_delete
AFTER DELETE ON personal FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'personal', 'DELETE', JSON_OBJECT('idPersonal', OLD.idPersonal, 'idEmpresa', OLD.idEmpresa, 'nombres', OLD.nombres, 'apellidos', OLD.apellidos, 'tipoDocumento', OLD.tipoDocumento, 'numeroDocumento', OLD.numeroDocumento, 'paisEmisorDocumento', OLD.paisEmisorDocumento, 'fechaNacimiento', OLD.fechaNacimiento, 'nacionalidad', OLD.nacionalidad, 'telefono', OLD.telefono, 'correo', OLD.correo, 'direccion', OLD.direccion, 'eliminado', OLD.eliminado), NULL);

CREATE TRIGGER trg_personal_tarea_insert
AFTER INSERT ON personal_tarea FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'personal_tarea', 'INSERT', NULL, JSON_OBJECT('idTarea', NEW.idTarea, 'idEmpresa', NEW.idEmpresa, 'idPersonal', NEW.idPersonal, 'idAlojamiento', NEW.idAlojamiento, 'fechaYHora', NEW.fechaYHora, 'descripcion', NEW.descripcion, 'estado', NEW.estado));

CREATE TRIGGER trg_personal_tarea_update
AFTER UPDATE ON personal_tarea FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'personal_tarea', 'UPDATE', JSON_OBJECT('idTarea', OLD.idTarea, 'idEmpresa', OLD.idEmpresa, 'idPersonal', OLD.idPersonal, 'idAlojamiento', OLD.idAlojamiento, 'fechaYHora', OLD.fechaYHora, 'descripcion', OLD.descripcion, 'estado', OLD.estado), JSON_OBJECT('idTarea', NEW.idTarea, 'idEmpresa', NEW.idEmpresa, 'idPersonal', NEW.idPersonal, 'idAlojamiento', NEW.idAlojamiento, 'fechaYHora', NEW.fechaYHora, 'descripcion', NEW.descripcion, 'estado', NEW.estado));

CREATE TRIGGER trg_personal_tarea_delete
AFTER DELETE ON personal_tarea FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'personal_tarea', 'DELETE', JSON_OBJECT('idTarea', OLD.idTarea, 'idEmpresa', OLD.idEmpresa, 'idPersonal', OLD.idPersonal, 'idAlojamiento', OLD.idAlojamiento, 'fechaYHora', OLD.fechaYHora, 'descripcion', OLD.descripcion, 'estado', OLD.estado), NULL);

CREATE TRIGGER trg_plataformas_insert
AFTER INSERT ON plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NULL, 'plataformas', 'INSERT', NULL, JSON_OBJECT('idPlataforma', NEW.idPlataforma, 'nombrePlataforma', NEW.nombrePlataforma));

CREATE TRIGGER trg_plataformas_update
AFTER UPDATE ON plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NULL, 'plataformas', 'UPDATE', JSON_OBJECT('idPlataforma', OLD.idPlataforma, 'nombrePlataforma', OLD.nombrePlataforma), JSON_OBJECT('idPlataforma', NEW.idPlataforma, 'nombrePlataforma', NEW.nombrePlataforma));

CREATE TRIGGER trg_plataformas_delete
AFTER DELETE ON plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NULL, 'plataformas', 'DELETE', JSON_OBJECT('idPlataforma', OLD.idPlataforma, 'nombrePlataforma', OLD.nombrePlataforma), NULL);

CREATE TRIGGER trg_empresas_plataformas_insert
AFTER INSERT ON empresas_plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'empresas_plataformas', 'INSERT', NULL, JSON_OBJECT('idEmpresa', NEW.idEmpresa, 'idPlataforma', NEW.idPlataforma, 'habilitado', NEW.habilitado));

CREATE TRIGGER trg_empresas_plataformas_update
AFTER UPDATE ON empresas_plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'empresas_plataformas', 'UPDATE', JSON_OBJECT('idEmpresa', OLD.idEmpresa, 'idPlataforma', OLD.idPlataforma, 'habilitado', OLD.habilitado), JSON_OBJECT('idEmpresa', NEW.idEmpresa, 'idPlataforma', NEW.idPlataforma, 'habilitado', NEW.habilitado));

CREATE TRIGGER trg_empresas_plataformas_delete
AFTER DELETE ON empresas_plataformas FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'empresas_plataformas', 'DELETE', JSON_OBJECT('idEmpresa', OLD.idEmpresa, 'idPlataforma', OLD.idPlataforma, 'habilitado', OLD.habilitado), NULL);

CREATE TRIGGER trg_estancias_insert
AFTER INSERT ON estancias FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'estancias', 'INSERT', NULL, JSON_OBJECT('idEstancia', NEW.idEstancia, 'idEmpresa', NEW.idEmpresa, 'idAlojamiento', NEW.idAlojamiento, 'idHuespedTitular', NEW.idHuespedTitular, 'estado', NEW.estado, 'entradaPrevista', NEW.entradaPrevista, 'salidaPrevista', NEW.salidaPrevista, 'checkIn', NEW.checkIn, 'checkOut', NEW.checkOut));

CREATE TRIGGER trg_estancias_update
AFTER UPDATE ON estancias FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'estancias', 'UPDATE', JSON_OBJECT('idEstancia', OLD.idEstancia, 'idEmpresa', OLD.idEmpresa, 'idAlojamiento', OLD.idAlojamiento, 'idHuespedTitular', OLD.idHuespedTitular, 'estado', OLD.estado, 'entradaPrevista', OLD.entradaPrevista, 'salidaPrevista', OLD.salidaPrevista, 'checkIn', OLD.checkIn, 'checkOut', OLD.checkOut), JSON_OBJECT('idEstancia', NEW.idEstancia, 'idEmpresa', NEW.idEmpresa, 'idAlojamiento', NEW.idAlojamiento, 'idHuespedTitular', NEW.idHuespedTitular, 'estado', NEW.estado, 'entradaPrevista', NEW.entradaPrevista, 'salidaPrevista', NEW.salidaPrevista, 'checkIn', NEW.checkIn, 'checkOut', NEW.checkOut));

CREATE TRIGGER trg_estancias_delete
AFTER DELETE ON estancias FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'estancias', 'DELETE', JSON_OBJECT('idEstancia', OLD.idEstancia, 'idEmpresa', OLD.idEmpresa, 'idAlojamiento', OLD.idAlojamiento, 'idHuespedTitular', OLD.idHuespedTitular, 'estado', OLD.estado, 'entradaPrevista', OLD.entradaPrevista, 'salidaPrevista', OLD.salidaPrevista, 'checkIn', OLD.checkIn, 'checkOut', OLD.checkOut), NULL);

CREATE TRIGGER trg_estancias_acompanantes_insert
AFTER INSERT ON estancias_acompanantes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'estancias_acompanantes', 'INSERT', NULL, JSON_OBJECT('idEstancia', NEW.idEstancia, 'idEmpresa', NEW.idEmpresa, 'idHuesped', NEW.idHuesped));

CREATE TRIGGER trg_estancias_acompanantes_update
AFTER UPDATE ON estancias_acompanantes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'estancias_acompanantes', 'UPDATE', JSON_OBJECT('idEstancia', OLD.idEstancia, 'idEmpresa', OLD.idEmpresa, 'idHuesped', OLD.idHuesped), JSON_OBJECT('idEstancia', NEW.idEstancia, 'idEmpresa', NEW.idEmpresa, 'idHuesped', NEW.idHuesped));

CREATE TRIGGER trg_estancias_acompanantes_delete
AFTER DELETE ON estancias_acompanantes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'estancias_acompanantes', 'DELETE', JSON_OBJECT('idEstancia', OLD.idEstancia, 'idEmpresa', OLD.idEmpresa, 'idHuesped', OLD.idHuesped), NULL);

CREATE TRIGGER trg_productos_insert
AFTER INSERT ON productos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'productos', 'INSERT', NULL, JSON_OBJECT('idProducto', NEW.idProducto, 'idEmpresa', NEW.idEmpresa, 'nombreProducto', NEW.nombreProducto, 'unidad', NEW.unidad, 'habilitado', NEW.habilitado));

CREATE TRIGGER trg_productos_update
AFTER UPDATE ON productos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'productos', 'UPDATE', JSON_OBJECT('idProducto', OLD.idProducto, 'idEmpresa', OLD.idEmpresa, 'nombreProducto', OLD.nombreProducto, 'unidad', OLD.unidad, 'habilitado', OLD.habilitado), JSON_OBJECT('idProducto', NEW.idProducto, 'idEmpresa', NEW.idEmpresa, 'nombreProducto', NEW.nombreProducto, 'unidad', NEW.unidad, 'habilitado', NEW.habilitado));

CREATE TRIGGER trg_productos_delete
AFTER DELETE ON productos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'productos', 'DELETE', JSON_OBJECT('idProducto', OLD.idProducto, 'idEmpresa', OLD.idEmpresa, 'nombreProducto', OLD.nombreProducto, 'unidad', OLD.unidad, 'habilitado', OLD.habilitado), NULL);

CREATE TRIGGER trg_almacenes_insert
AFTER INSERT ON almacenes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'almacenes', 'INSERT', NULL, JSON_OBJECT('idAlmacen', NEW.idAlmacen, 'idEmpresa', NEW.idEmpresa, 'tipo', NEW.tipo, 'idInmueble', NEW.idInmueble, 'idAlojamiento', NEW.idAlojamiento));

CREATE TRIGGER trg_almacenes_update
AFTER UPDATE ON almacenes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'almacenes', 'UPDATE', JSON_OBJECT('idAlmacen', OLD.idAlmacen, 'idEmpresa', OLD.idEmpresa, 'tipo', OLD.tipo, 'idInmueble', OLD.idInmueble, 'idAlojamiento', OLD.idAlojamiento), JSON_OBJECT('idAlmacen', NEW.idAlmacen, 'idEmpresa', NEW.idEmpresa, 'tipo', NEW.tipo, 'idInmueble', NEW.idInmueble, 'idAlojamiento', NEW.idAlojamiento));

CREATE TRIGGER trg_almacenes_delete
AFTER DELETE ON almacenes FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'almacenes', 'DELETE', JSON_OBJECT('idAlmacen', OLD.idAlmacen, 'idEmpresa', OLD.idEmpresa, 'tipo', OLD.tipo, 'idInmueble', OLD.idInmueble, 'idAlojamiento', OLD.idAlojamiento), NULL);

CREATE TRIGGER trg_kardex_movimientos_insert
AFTER INSERT ON kardex_movimientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'kardex_movimientos', 'INSERT', NULL, JSON_OBJECT('idMovimiento', NEW.idMovimiento, 'idEmpresa', NEW.idEmpresa, 'idProducto', NEW.idProducto, 'tipo', NEW.tipo, 'idAlmacenOrigen', NEW.idAlmacenOrigen, 'idAlmacenDestino', NEW.idAlmacenDestino, 'cantidad', NEW.cantidad, 'motivo', NEW.motivo, 'fecha', NEW.fecha));

CREATE TRIGGER trg_kardex_movimientos_update
AFTER UPDATE ON kardex_movimientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (NEW.idEmpresa, 'kardex_movimientos', 'UPDATE', JSON_OBJECT('idMovimiento', OLD.idMovimiento, 'idEmpresa', OLD.idEmpresa, 'idProducto', OLD.idProducto, 'tipo', OLD.tipo, 'idAlmacenOrigen', OLD.idAlmacenOrigen, 'idAlmacenDestino', OLD.idAlmacenDestino, 'cantidad', OLD.cantidad, 'motivo', OLD.motivo, 'fecha', OLD.fecha), JSON_OBJECT('idMovimiento', NEW.idMovimiento, 'idEmpresa', NEW.idEmpresa, 'idProducto', NEW.idProducto, 'tipo', NEW.tipo, 'idAlmacenOrigen', NEW.idAlmacenOrigen, 'idAlmacenDestino', NEW.idAlmacenDestino, 'cantidad', NEW.cantidad, 'motivo', NEW.motivo, 'fecha', NEW.fecha));

CREATE TRIGGER trg_kardex_movimientos_delete
AFTER DELETE ON kardex_movimientos FOR EACH ROW
INSERT INTO logs_gestalo (idEmpresa, entidad, operacion, datosAnteriores, datosNuevos)
VALUES (OLD.idEmpresa, 'kardex_movimientos', 'DELETE', JSON_OBJECT('idMovimiento', OLD.idMovimiento, 'idEmpresa', OLD.idEmpresa, 'idProducto', OLD.idProducto, 'tipo', OLD.tipo, 'idAlmacenOrigen', OLD.idAlmacenOrigen, 'idAlmacenDestino', OLD.idAlmacenDestino, 'cantidad', OLD.cantidad, 'motivo', OLD.motivo, 'fecha', OLD.fecha), NULL);

-- Requiere event_scheduler habilitado por el administrador del servidor.
-- El evento se programa en UTC; no elimina datos de las tablas de negocio.
CREATE EVENT ev_gestalo_purgar_logs
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_TIMESTAMP + INTERVAL 1 DAY
    DO DELETE FROM logs_gestalo WHERE fecha < UTC_TIMESTAMP(6) - INTERVAL 6 MONTH;
