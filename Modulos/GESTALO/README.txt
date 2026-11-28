GESTALO - menu y calendario

Contenido de esta etapa
----------------------
El menu declara los accesos solicitados. Los accesos sin pagina propia se
muestran como "Seccion en construccion" en el panel anfitrion.

Disponibles y Ocupados agrupan los alojamientos habilitados y no eliminados
por inmueble habilitado y no eliminado. Se usa el campo disponible del
alojamiento: 1 es disponible y 0 es ocupado. Un inmueble con ambos tipos
aparece en ambas listas. Las listas solo contienen datos de la empresa del
registro recibido del anfitrion. Para multiempresa el anfitrion crea los
grupos y agrega el parametro empresa a los enlaces; para una sola empresa
el modulo lo incluye en los enlaces del calendario. El calendario requiere
la sesion y llama a empresaModulo('GESTALO') antes de consultar sus datos.
Si falla la conexion o faltan tablas, las listas muestran un aviso de error.

Cada alojamiento abre un calendario: las noches de estancias reservadas o
en curso se muestran en negrita. El primer clic selecciona check-in y el
segundo check-out; el rango se resalta y no puede incluir noches reservadas
ni comenzar en el pasado. El check-out es exclusivo: puede coincidir con
el check-in de otra estancia. Se usa la zona horaria del inmueble.

Esta seleccion NO crea ni guarda reservas y no solicita huesped. La gestion
de huespedes, las reservas persistentes, el alquiler, el desalojo, la
simulacion de ingresos, el IGV y los demas accesos aun no estan implementados.
Las fechas visualizadas proceden de entradaPrevista y salidaPrevista de
estancias activas. Una estancia activa sin fechas previstas validas impide
mostrar el calendario y genera un error, para no presentar dias como libres
cuando no se puede determinar su estado.

El kardex esta pendiente de implementacion. Su esquema preve contar
productos por ubicacion mediante entradas, salidas y traslados, sin
registrar precios ni gastos.

Instalacion
-----------
1. Verificar el contrato y las rutas de la version instalada de OpenDoors V2.
   Comparar cualquier carpeta GESTALO existente antes de reemplazar archivos.
2. Copiar esta carpeta completa a Modulos/GESTALO del anfitrion. Mantener
   Nucleo/util protegido contra acceso HTTP directo y comprobar que la regla
   correspondiente de Apache este activa. El modulo necesita las utilidades
   verificadas util/Seguridad/verificar.php, util/Seguridad/modulos.php,
   util/BaseDatos/db.php y util/BaseDatos/conexion_mysql.php. El anfitrion
   debe agrupar por empresa y validar cuenta, empresa, habilitacion y licencia
   mediante empresaModulo. El anfitrion independiente tambien implementa
   ese contrato; la vista ficticia ElementosVistaBasica solo simula una
   empresa.
3. Registrar el nombre exacto GESTALO en la tabla general modulos y asignar
   y habilitarlo en empresas_modulos para la empresa correspondiente. Usar
   el procedimiento administrativo adecuado al esquema real del anfitrion.
4. Configurar DB_NAME_MODULO en el entorno del proceso PHP. La conexion
   reutiliza DB_HOST, DB_PORT, DB_USER y DB_PASS del anfitrion; por tanto,
   requiere que la base GESTALO este en ese mismo servidor y que esa cuenta
   pueda acceder a ella. No incluir contrasenas en el codigo ni en este
   archivo. Revisar la configuracion y verificacion TLS del anfitrion.
5. Con autorizacion, preparar el esquema MySQL 8.4 y permisos necesarios.
   Nucleo/util/esquema.sql es un esquema inicial para una base vacia, no una
   migracion ni un script que pueda repetirse sin mas. No ejecutarlo sobre
   datos existentes sin respaldo y un plan de migracion.
   Su clave foranea apunta literalmente a empresas.empresas; comprobar que
   ese sea el nombre real de la base general configurada por el anfitrion y
   que este en el mismo servidor. La relacion restringe el borrado de la
   empresa del anfitrion mientras exista su registro local.
   La instalacion requiere permisos CREATE, TRIGGER y EVENT en la base de
   GESTALO y REFERENCES en empresas.empresas. La purga de auditoria requiere
   event_scheduler habilitado y permiso DELETE del DEFINER sobre logs_gestalo;
   se ejecuta una vez al dia y debe supervisarse. Acordar la retencion de
   copias de seguridad y el acceso a los datos personales de auditoria.
6. El registro local empresas debe tener el mismo idEmpresa del anfitrion
   antes de guardar sus datos. El modulo no lo crea automaticamente.
7. El esquema guarda instantes de estancias en UTC. El modulo conecta en
   UTC y convierte las fechas a la zona IANA del inmueble. Antes de cargar
   reservas debe existir una politica coherente para crear esas fechas.

Pruebas
-------
Desde la carpeta del modulo ejecutar php tests/consultas.php con PDO SQLite
disponible. En el proyecto independiente, php tests/menu_multiempresa.php
verifica el menu con la vista previa y datos ficticios. Tambien se probaron
copias aisladas del panel y la autorizacion del anfitrion con SQLite.
Falta validar la integracion instalada con cuentas de prueba del anfitrion.
No se ha autorizado ni ejecutado la instalacion en la base real.
