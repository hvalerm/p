VISTA PREVIA LOCAL DE GESTALO - NO ES EL MODULO DE PRODUCCION

Este directorio contiene una copia del panel del anfitrion y del modulo,
ademas de dobles de sesion y datos sinteticos en SQLite en memoria. No
conecta con bases reales ni guarda cambios. Solo sirve para ver el menu y
el calendario; no valida una sesion ni la integracion real. El calendario
no guarda reservas y las demas secciones estan en construccion.

Requisitos: PHP 8.2 o compatible con mbstring, PDO y pdo_sqlite habilitados.
Desde la raiz de este proyecto, iniciar:

  php -S 127.0.0.1:8089 -t . ElementosVistaBasica\router.php

Abrir http://127.0.0.1:8089/ElementosVistaBasica/ModoAnfitrion/vistaAnfitrion.php
y detener el servidor con Ctrl+C. Usar otro
puerto si el indicado esta ocupado. El router limita los archivos servidos;
no ejecutar esta vista previa con otro servidor ni publicarla en red. La
sesion es ficticia, no protege informacion y permite acceso a cualquier
usuario local. El panel acepta IPv4 privadas si el servidor escucha en la
red, y bloquea solicitudes reenviadas; mantenlo ligado a localhost.

La copia del panel y del modulo es una instantanea: los cambios posteriores
en el anfitrion o en Modulos/GESTALO no se reflejan automaticamente. Para
actualizarla, revisar y copiar de nuevo los archivos correspondientes,
conservando las adaptaciones de esta vista previa.

No copiar este directorio al anfitrion ni reutilizar sus dobles de sesion o
conexion en produccion. Para instalar el modulo real consultar
Modulos/GESTALO/README.txt. La vista previa no prueba la licencia real,
las autorizaciones reales ni MySQL.
