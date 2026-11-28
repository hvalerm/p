<?php

const GESTALO_ICONO_INMUEBLE = 'M2 11 12 3l10 8-1.3 1.6L20 12v9h-6v-6h-4v6H4v-9l-.7.6z';

function gestaloModuloHabilitado(PDO $pdo, int $idEmpresa): bool
{
    $stmt = $pdo->prepare(
        'SELECT m.nombreModulo
         FROM empresas_modulos em
         INNER JOIN modulos m ON m.idModulo = em.idModulo
         WHERE em.idEmpresa = ? AND em.habilitado = 1 AND DATE(em.licenciaExpira) >= CURRENT_DATE'
    );
    $stmt->execute([$idEmpresa]);
    return in_array('GESTALO', $stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function gestaloListarAlojamientos(PDO $pdo, int $idEmpresa): array
{
    $stmt = $pdo->prepare(
        'SELECT i.idInmueble, i.nombreInmueble, a.idAlojamiento,
                a.nombreAlojamiento, a.disponible
         FROM inmuebles i
         INNER JOIN alojamientos a
             ON a.idEmpresa = i.idEmpresa AND a.idInmueble = i.idInmueble
         WHERE i.idEmpresa = ? AND i.habilitado = 1 AND i.eliminado = 0
             AND a.habilitado = 1 AND a.eliminado = 0
         ORDER BY i.nombreInmueble, i.idInmueble, a.nombreAlojamiento, a.idAlojamiento'
    );
    $stmt->execute([$idEmpresa]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gestaloListasMenu(array $alojamientos, ?int $idEmpresa = null): array
{
    $grupos = ['disponibles' => [], 'ocupados' => []];
    foreach ($alojamientos as $alojamiento) {
        $disponible = (string) $alojamiento['disponible'];
        if ($disponible !== '0' && $disponible !== '1') {
            throw new UnexpectedValueException('Disponibilidad de alojamiento no valida.');
        }
        $tipo = $disponible === '1' ? 'disponibles' : 'ocupados';
        $idInmueble = (int) $alojamiento['idInmueble'];
        $idAlojamiento = (int) $alojamiento['idAlojamiento'];
        if (!isset($grupos[$tipo][$idInmueble])) {
            $grupos[$tipo][$idInmueble] = [
                $alojamiento['nombreInmueble'], [], null, 'inmueble-' . $idInmueble, GESTALO_ICONO_INMUEBLE,
            ];
        }
        $grupos[$tipo][$idInmueble][1][] = [
            $alojamiento['nombreAlojamiento'],
            null,
            '../Modulos/GESTALO/Nucleo/calendario.php?id=' . $idAlojamiento
                . ($idEmpresa !== null ? '&empresa=' . $idEmpresa : ''),
            'alojamiento-' . $idAlojamiento,
        ];
    }

    return [
        'disponibles' => array_values($grupos['disponibles']),
        'ocupados' => array_values($grupos['ocupados']),
    ];
}

function gestaloBuscarAlojamiento(PDO $pdo, int $idEmpresa, int $idAlojamiento): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.idAlojamiento, a.nombreAlojamiento, a.disponible,
                i.nombreInmueble, i.zonaHoraria
         FROM alojamientos a
         INNER JOIN inmuebles i
             ON i.idEmpresa = a.idEmpresa AND i.idInmueble = a.idInmueble
         WHERE a.idEmpresa = ? AND a.idAlojamiento = ?
             AND a.habilitado = 1 AND a.eliminado = 0
             AND i.habilitado = 1 AND i.eliminado = 0'
    );
    $stmt->execute([$idEmpresa, $idAlojamiento]);
    $alojamiento = $stmt->fetch(PDO::FETCH_ASSOC);
    return $alojamiento === false ? null : $alojamiento;
}

function gestaloReservasCalendario(PDO $pdo, int $idEmpresa, int $idAlojamiento, DateTimeZone $zona): array
{
    $stmt = $pdo->prepare(
        "SELECT entradaPrevista, salidaPrevista
         FROM estancias
         WHERE idEmpresa = ? AND idAlojamiento = ? AND estado IN ('reservada', 'en curso')
         ORDER BY entradaPrevista, idEstancia"
    );
    $stmt->execute([$idEmpresa, $idAlojamiento]);
    $utc = new DateTimeZone('UTC');
    $reservas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $estancia) {
        $entrada = $estancia['entradaPrevista'];
        $salida = $estancia['salidaPrevista'];
        if (!is_string($entrada) || !is_string($salida)) {
            throw new UnexpectedValueException('Una estancia activa no tiene fechas previstas.');
        }
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $entrada, $utc);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $salida, $utc);
        if ($inicio === false || $fin === false
            || $inicio->format('Y-m-d H:i:s') !== $entrada
            || $fin->format('Y-m-d H:i:s') !== $salida
            || $fin <= $inicio) {
            throw new UnexpectedValueException('Una estancia activa tiene fechas invalidas.');
        }
        $diaInicio = $inicio->setTimezone($zona)->format('Y-m-d');
        $diaFin = $fin->setTimezone($zona)->format('Y-m-d');
        if ($diaFin <= $diaInicio) {
            throw new UnexpectedValueException('Una estancia activa no ocupa al menos una noche.');
        }
        $reservas[] = ['inicio' => $diaInicio, 'fin' => $diaFin];
    }
    return $reservas;
}
