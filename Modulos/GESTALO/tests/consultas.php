<?php
require_once __DIR__ . '/../Nucleo/util/consultas.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE inmuebles (idInmueble INTEGER, idEmpresa INTEGER, nombreInmueble TEXT, zonaHoraria TEXT, habilitado INTEGER, eliminado INTEGER)');
$pdo->exec('CREATE TABLE alojamientos (idAlojamiento INTEGER, idEmpresa INTEGER, idInmueble INTEGER, nombreAlojamiento TEXT, disponible INTEGER, habilitado INTEGER, eliminado INTEGER)');
$pdo->exec('CREATE TABLE estancias (idEstancia INTEGER, idEmpresa INTEGER, idAlojamiento INTEGER, estado TEXT, entradaPrevista TEXT, salidaPrevista TEXT)');
$pdo->exec('CREATE TABLE modulos (idModulo INTEGER, nombreModulo TEXT)');
$pdo->exec('CREATE TABLE empresas_modulos (idEmpresa INTEGER, idModulo INTEGER, habilitado INTEGER, licenciaExpira TEXT)');
$pdo->exec("INSERT INTO inmuebles VALUES
    (1, 1, 'Casa A', 'America/Lima', 1, 0), (2, 1, 'Casa deshabilitada', 'America/Lima', 0, 0),
    (3, 2, 'Otra empresa', 'America/Lima', 1, 0)");
$pdo->exec("INSERT INTO alojamientos VALUES
    (1, 1, 1, 'Cuarto disponible', 1, 1, 0), (2, 1, 1, 'Cuarto ocupado', 0, 1, 0),
    (3, 1, 1, 'Cuarto deshabilitado', 1, 0, 0), (4, 1, 2, 'Casa deshabilitada', 1, 1, 0),
    (5, 2, 3, 'Otra empresa', 1, 1, 0), (6, 1, 1, 'Cuarto eliminado', 1, 1, 1)");
$pdo->exec("INSERT INTO modulos VALUES (1, 'GESTALO'), (2, 'gestalo')");
$pdo->exec("INSERT INTO empresas_modulos VALUES (1, 1, 1, '9999-12-31'), (2, 1, 0, '9999-12-31'), (3, 2, 1, '9999-12-31'), (4, 1, 1, '2000-01-01')");

comprobar(gestaloModuloHabilitado($pdo, 1), 'No reconoce el modulo habilitado.');
comprobar(!gestaloModuloHabilitado($pdo, 2), 'Acepta un modulo deshabilitado.');
comprobar(!gestaloModuloHabilitado($pdo, 3), 'La comparacion del nombre no es exacta.');
comprobar(!gestaloModuloHabilitado($pdo, 4), 'Acepta una licencia de modulo expirada.');

$listas = gestaloListasMenu(gestaloListarAlojamientos($pdo, 1));
comprobar(count($listas['disponibles']) === 1 && count($listas['ocupados']) === 1, 'La casa mixta no aparece en ambas listas.');
comprobar($listas['disponibles'][0][1][0][0] === 'Cuarto disponible', 'La lista disponible contiene un cuarto incorrecto.');
foreach (['disponibles', 'ocupados'] as $tipo) {
    comprobar(!empty($listas[$tipo][0][4]), 'El inmueble no tiene icono.');
    comprobar(empty($listas[$tipo][0][1][0][4]), 'El acceso al alojamiento tiene un icono inesperado.');
}
comprobar(count($listas['disponibles'][0][1]) === 1 && count($listas['ocupados'][0][1]) === 1, 'Incluye cuartos deshabilitados, eliminados o de otra empresa.');
comprobar(str_contains($listas['ocupados'][0][1][0][2], 'calendario.php?id=2'), 'El acceso no apunta al calendario correcto.');
comprobar(gestaloBuscarAlojamiento($pdo, 1, 5) === null, 'Permite consultar un alojamiento de otra empresa.');
comprobar(gestaloBuscarAlojamiento($pdo, 1, 3) === null, 'Permite consultar un alojamiento deshabilitado.');

$pdo->exec("INSERT INTO estancias VALUES
    (1, 1, 1, 'reservada', '2027-01-02 05:00:00', '2027-01-04 05:00:00'),
    (2, 1, 1, 'cancelada', '2027-01-05 05:00:00', '2027-01-06 05:00:00'),
    (3, 2, 1, 'reservada', '2027-01-07 05:00:00', '2027-01-08 05:00:00')");
$reservas = gestaloReservasCalendario($pdo, 1, 1, new DateTimeZone('America/Lima'));
comprobar($reservas === [['inicio' => '2027-01-02', 'fin' => '2027-01-04']], 'No respeta zona, empresa o estados de reserva.');
$pdo->exec("INSERT INTO estancias VALUES (4, 1, 1, 'en curso', NULL, NULL)");
try {
    gestaloReservasCalendario($pdo, 1, 1, new DateTimeZone('America/Lima'));
    throw new RuntimeException('Acepta una estancia activa sin fechas.');
} catch (UnexpectedValueException $e) {
    comprobar(str_contains($e->getMessage(), 'fechas'), 'Error inesperado al validar fechas.');
}

echo "Pruebas de consultas y aislamiento: correctas.\n";
