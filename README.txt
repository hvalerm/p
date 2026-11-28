ANFITRION INDEPENDIENTE CON DATOS REALES

El panel y el inicio de sesion estan fuera de Modulos/GESTALO. El panel
consulta los modulos habilitados para la empresa y carga sus menus desde
Modulos. Si se elimina un modulo, el panel sigue disponible y muestra
"Modulo no disponible" para cualquier asignacion que quede en la base.
Cada modulo debe comprobar por servidor su sesion, empresa y autorizacion.
El login acepta cuentas con varias empresas habilitadas sin solicitar una
seleccion. La licencia de la empresa no bloquea la sesion; el acceso se
determina por la licencia de cada modulo, vigente hasta el final de su fecha.
El panel agrupa por empresa para cuentas multiempresa y conserva las empresas
con modulo vencido mostrando un mensaje sin cargar sus funciones.
Cada pagina valida el parametro empresa con empresaModulo, sin cambiar la
empresa de otras pestanas. Se siguen rechazando cuentas y empresas
deshabilitadas o no vinculadas.

Esta instalacion consulta las bases empresas y gestalo en el servidor
configurado mediante variables de entorno. La conexion MySQL solicita TLS,
pero no verifica el certificado del servidor. No requiere un archivo CA.
Esta configuracion no protege contra la suplantacion del servidor MySQL
y no se recomienda para datos reales en produccion.
El usuario de base de datos debe tener los permisos necesarios, incluidos
los de escritura sobre empresas.intentos_login para el control de acceso.
El login y la consulta de datos usan la base real; el calendario aun no
guarda reservas. Las secciones sin pagina estan en construccion.

SEGURIDAD

Se retiraron las credenciales que estaban en util/db.php. Rota la contrasena
anterior y no publiques secretos en GitHub. No uses
el servidor integrado de PHP ni otro servidor sin configurar: pueden
exponer archivos privados. El script HTTPS solo publica las rutas previstas;
al integrar otro modulo revisa sus rutas y sus controles de autorizacion.
No publiques ni compartas la clave de la autoridad local o del servidor.
Al instalar una autoridad local en otro dispositivo, ese dispositivo
confia en los certificados que dicha autoridad pueda firmar.

VARIABLES DE ENTORNO

Configurar en el proceso PHP (en Render: Environment):
  DB_HOST           Host del servidor MySQL.
  DB_PORT           Puerto MySQL (entero de 1 a 65535).
  DB_USER           Usuario MySQL.
  DB_PASS           Contrasena MySQL; no guardarla en el repositorio.
  DB_NAME_EMPRESAS  empresas (o el nombre real de la base general).
  DB_NAME_MODULO    gestalo (o el nombre real de la base del modulo).

DB_NAME_GESTALO se reemplazo por DB_NAME_MODULO. No se leen archivos .env
automaticamente. DB_SSL_CA ya no se utiliza.
La vista ficticia configura DB_NAME_MODULO=vista_previa solo en su proceso.
En PowerShell se asignan con $env:NOMBRE_VARIABLE = 'valor' antes de iniciar
el servidor. El proceso Apache hereda esas variables. No guardes comandos
con contrasenas en scripts compartidos ni en archivos versionados.

HTTPS LOCAL CON XAMPP

El Dockerfile no usa Composer porque este proyecto no tiene dependencias
de Composer. Instala PDO MySQL y mbstring y bloquea el acceso HTTP a util,
tests, menus internos y archivos de configuracion o certificados.
En Render se reconoce HTTPS mediante X-Forwarded-Proto solo cuando la
variable automatica RENDER es true. Fuera de Render se requiere HTTPS
directo; no configures RENDER=true en servidores expuestos sin ese proxy.
El script iniciar-https.ps1 es exclusivo de las pruebas locales con XAMPP.

Requiere Apache y PHP de XAMPP en C:\xampp, PowerShell, PDO MySQL y mbstring.
Conecta el celular a la misma red Wi-Fi. En PowerShell ejecuta ipconfig y
busca la direccion IPv4 de Wi-Fi. Desde la raiz del proyecto ejecuta:

  .\iniciar-https.ps1 -Direccion 192.168.3.4

Sustituye la direccion por la IPv4 actual. El servidor escucha unicamente
en esa direccion y en 127.0.0.1, puerto 8443, hasta pulsar Ctrl+C. Si la IP
cambia, vuelve a ejecutar el comando con la nueva IP. El script genera una
autoridad y un certificado en %LOCALAPPDATA%\OpenDoorsVista, no dentro de
la carpeta publicada. Protege esa carpeta y especialmente autoridad.key.

Instala el archivo publico autoridad.crt como autoridad de confianza en la
computadora y en el celular, siguiendo las instrucciones del sistema de
cada dispositivo. En iOS hay que habilitar tambien la confianza completa
del certificado. Asegurate de transferirlo por un medio confiable. No
instales ni compartas autoridad.key. Luego abre https://IP-DE-WIFI:8443/.
Si no conecta, permite Apache en el Firewall de Windows para redes privadas.
No uses redireccion de puertos ni publiques este servidor en Internet.

Para validar sin datos reales: php tests\anfitrion.php y
php Modulos\GESTALO\tests\consultas.php. El inicio de sesion autentica
cuentas existentes de la base empresas; no crea usuarios. Las pruebas
automaticas no verifican un inicio de sesion real ni la confianza del
certificado en el celular.
