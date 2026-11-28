GESTALO - esquema propuesto

bd.sql es un esquema inicial para una base gestalo VACIA en MySQL 8.4.
No actualiza tablas existentes ni convierte los datos del esquema anterior.
No lo ejecute en una base real sin autorizacion, respaldo y un plan de migracion.
Seleccionar la base gestalo antes de importar el archivo con un cliente MySQL.
El archivo crea tablas, triggers y un evento; no crea la base ni usuarios.

La tabla local empresas solo contiene el idEmpresa del anfitrion y tiene una
clave foranea a empresas.empresas en el mismo servidor. La cuenta instaladora
necesita permisos CREATE, TRIGGER y EVENT en gestalo y REFERENCES en
empresas.empresas. Esta relacion impide eliminar la empresa del anfitrion
mientras exista su registro en GESTALO. La aplicacion debera comprobar la
sesion, la habilitacion del modulo y la empresa antes de crear ese registro;
no debe confiar en un identificador proporcionado por el cliente.

El SQL usa utf8mb4, InnoDB y la intercalacion de MySQL 8.0. No se ha
verificado su compatibilidad con MariaDB. Cada conexion de la aplicacion
debe ejecutar SET time_zone = '+00:00'. Las fechas y horas de estancias,
tareas, kardex y auditoria representan UTC; las fechas de nacimiento son
fechas civiles. La aplicacion convierte las fechas de y hacia la zona IANA
del inmueble, valida esa zona y resuelve las horas locales ambiguas o
inexistentes. America/Lima es el valor predeterminado del inmueble.

Cada inmueble pertenece a una empresa y agrupa alojamientos. La disponibilidad
debe calcularse considerando la habilitacion y eliminacion del inmueble y
alojamiento, la disponibilidad del alojamiento y las estancias activas. No
hay una columna de disponibilidad agregada del inmueble que pueda quedar
desactualizada. Las banderas no previenen por si solas asignaciones invalidas.

Antes de crear o modificar una estancia, la aplicacion debe bloquear la fila
del alojamiento dentro de la transaccion y comprobar las estancias activas
que se solapan. Tambien debe validar las fechas, transiciones de estado,
condiciones de check-in/check-out y propiedad de todos los recursos. El SQL
no impide por si solo solapamientos ni garantiza todas esas reglas.

El kardex usa cantidades positivas; entrada suma en el destino, salida resta
del origen y traslado resta del origen y suma al destino. Los almacenes
representan la empresa, un inmueble o un alojamiento. El inventario por
producto y ubicacion se determina a partir de sus movimientos. El kardex
no registra precios, moneda ni gastos.

Antes de registrar una salida o traslado, la aplicacion debe bloquear, en
orden consistente, el producto y los almacenes implicados; calcular sus
saldos mediante lecturas actuales, no una instantanea anterior de REPEATABLE
READ, y rechazar un resultado negativo dentro de la misma transaccion.
Los movimientos no son inmutables por el esquema: la aplicacion debe
controlar modificaciones y eliminaciones y recalcular sus efectos. Las
reglas de saldos, concurrencia y acceso no estan implementadas en este SQL.

Los triggers guardan valores completos, incluidos datos personales, en
logs_gestalo. No registran la identidad del usuario que hizo el cambio.
Restringir su acceso y filtrar por empresa. El evento elimina registros con
mas de seis meses una vez al dia, por lo que su permanencia puede exceder
ese plazo hasta la siguiente ejecucion. Requiere event_scheduler habilitado,
permisos efectivos del DEFINER y supervision de su funcionamiento; si el
evento no se ejecuta, los logs no se purgan; el DEFINER tambien necesita
permiso DELETE sobre logs_gestalo. La retencion de copias de
seguridad debe gestionarse aparte.

drop.sql y truncate.sql corresponden al esquema anterior y NO deben usarse
con esta version; ademas son destructivos. No se han ejecutado.

Una copia del esquema se entrega en Modulos/GESTALO/Nucleo/util/esquema.sql.
Mantener ambas versiones sincronizadas al modificar el esquema.
