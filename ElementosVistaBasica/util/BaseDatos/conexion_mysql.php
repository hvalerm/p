<?php
function conexionVistaPrevia(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('CREATE TABLE modulos (idModulo INTEGER PRIMARY KEY, nombreModulo TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE empresas_modulos (idEmpresa INTEGER, idModulo INTEGER, habilitado INTEGER, licenciaExpira TEXT)');
    $pdo->exec('CREATE TABLE inmuebles (idInmueble INTEGER, idEmpresa INTEGER, nombreInmueble TEXT, zonaHoraria TEXT, habilitado INTEGER, eliminado INTEGER)');
    $pdo->exec('CREATE TABLE alojamientos (idAlojamiento INTEGER, idEmpresa INTEGER, idInmueble INTEGER, nombreAlojamiento TEXT, disponible INTEGER, habilitado INTEGER, eliminado INTEGER)');
    $pdo->exec('CREATE TABLE estancias (idEstancia INTEGER, idEmpresa INTEGER, idAlojamiento INTEGER, estado TEXT, entradaPrevista TEXT, salidaPrevista TEXT)');
    $pdo->exec("INSERT INTO modulos VALUES (1, 'GESTALO')");
    $pdo->exec("INSERT INTO empresas_modulos VALUES (1, 1, 1, '9999-12-31')");
    $pdo->exec("INSERT INTO inmuebles VALUES
        (1, 1, 'Casa de ejemplo', 'America/Lima', 1, 0),
        (2, 1, 'Edificio de ejemplo', 'America/Lima', 1, 0)");
    $pdo->exec("INSERT INTO alojamientos VALUES
        (1, 1, 1, 'Habitación 1', 1, 1, 0),
        (2, 1, 1, 'Habitación 2', 0, 1, 0),
        (3, 1, 2, 'Habitación 3', 1, 1, 0)");
    $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
    $utc = new DateTimeZone('UTC');
    $reserva = $pdo->prepare("INSERT INTO estancias VALUES (1, 1, 1, 'reservada', ?, ?)");
    $reserva->execute([
        $hoy->modify('+3 days')->setTimezone($utc)->format('Y-m-d H:i:s'),
        $hoy->modify('+6 days')->setTimezone($utc)->format('Y-m-d H:i:s'),
    ]);
    $estancia = $pdo->prepare("INSERT INTO estancias VALUES (2, 1, 2, 'en curso', ?, ?)");
    $estancia->execute([
        $hoy->modify('-1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        $hoy->modify('+2 days')->setTimezone($utc)->format('Y-m-d H:i:s'),
    ]);
    return $pdo;
}

function crearConexionPDO(string $nombre, string $zonaHoraria): PDO
{
    if ($nombre !== 'vista_previa' || $zonaHoraria !== '+00:00') {
        throw new RuntimeException('Configuracion no valida para la vista previa.');
    }
    return conexionVistaPrevia();
}
