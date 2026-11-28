-- DESACTUALIZADO: corresponde al esquema anterior. NO EJECUTAR.
-- Este script es destructivo y no contempla el esquema actual.
-- TRUNCATE no dispara triggers y elimina tambien el historial de auditoria.
-- Ejecutar todo en la misma sesion. Si hay un error, restaurar FOREIGN_KEY_CHECKS
-- con la ultima sentencia antes de continuar usando la conexion.
SET @gestalo_foreign_key_checks = @@SESSION.FOREIGN_KEY_CHECKS;
SET SESSION FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE personal_tarea;
TRUNCATE TABLE empresas_plataformas;
TRUNCATE TABLE estancias_acompanantes;
TRUNCATE TABLE estancias;
TRUNCATE TABLE lugares;
TRUNCATE TABLE zonas;
TRUNCATE TABLE empresas;
TRUNCATE TABLE huespedes;
TRUNCATE TABLE personal;
TRUNCATE TABLE plataformas;
TRUNCATE TABLE logs_lugares;
TRUNCATE TABLE logs_zonas;
TRUNCATE TABLE logs_empresas;
TRUNCATE TABLE logs_huespedes;
TRUNCATE TABLE logs_personal;
TRUNCATE TABLE logs_estancias_acompanantes;
TRUNCATE TABLE logs_estancias;
TRUNCATE TABLE logs_plataformas;
TRUNCATE TABLE logs_empresas_plataformas;
TRUNCATE TABLE logs_personal_tarea;

SET SESSION FOREIGN_KEY_CHECKS = @gestalo_foreign_key_checks;