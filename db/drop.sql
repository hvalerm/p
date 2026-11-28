-- DESACTUALIZADO: corresponde al esquema anterior. NO EJECUTAR.
-- Este script es destructivo y no contempla el esquema actual.
-- DROP TABLE elimina tambien los triggers asociados a cada tabla.
DROP TABLE IF EXISTS personal_tarea;
DROP TABLE IF EXISTS empresas_plataformas;
DROP TABLE IF EXISTS estancias_acompanantes;
DROP TABLE IF EXISTS estancias;
DROP TABLE IF EXISTS lugares;
DROP TABLE IF EXISTS zonas;
DROP TABLE IF EXISTS empresas;
DROP TABLE IF EXISTS huespedes;
DROP TABLE IF EXISTS personal;
DROP TABLE IF EXISTS plataformas;
DROP TABLE IF EXISTS logs_lugares;
DROP TABLE IF EXISTS logs_zonas;
DROP TABLE IF EXISTS logs_empresas;
DROP TABLE IF EXISTS logs_huespedes;
DROP TABLE IF EXISTS logs_personal;
DROP TABLE IF EXISTS logs_estancias_acompanantes;
DROP TABLE IF EXISTS logs_estancias;
DROP TABLE IF EXISTS logs_plataformas;
DROP TABLE IF EXISTS logs_empresas_plataformas;
DROP TABLE IF EXISTS logs_personal_tarea;