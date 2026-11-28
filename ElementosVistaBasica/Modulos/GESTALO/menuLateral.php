<?php
require_once __DIR__ . '/Nucleo/util/db.php';
require_once __DIR__ . '/Nucleo/util/consultas.php';

return static function (array $modulo, string $nombreWeb): array {
    $icono = 'M2 4h2v9h7V8h7a4 4 0 0 1 4 4v8h-2v-3H4v3H2zM5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z';
    $iconoPropiedades = GESTALO_ICONO_INMUEBLE;
    $iconoPersonal = 'M9 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm7 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM2 19c0-3.3 3.1-6 7-6s7 2.7 7 6v2H2zm14-6c3.3 0 6 2.2 6 5v3h-4v-2c0-2.3-.9-4.4-2.4-6z';
    $iconoInventario = 'M3 4h18v5H3zm1 7h16v10H4zm6 2v2h4v-2z';
    $listas = ['disponibles' => [], 'ocupados' => []];
    $mensajeDisponibles = 'No hay alojamientos disponibles.';
    $mensajeOcupados = 'No hay alojamientos ocupados.';
    try {
        $idEmpresa = filter_var($modulo['idEmpresa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idEmpresa === false) {
            throw new UnexpectedValueException('Falta la empresa del modulo.');
        }
        $listas = gestaloListasMenu(
            gestaloListarAlojamientos(getGestaloPDO(), $idEmpresa),
            empty($modulo['multiempresa']) ? $idEmpresa : null
        );
    } catch (PDOException | RuntimeException | UnexpectedValueException $e) {
        error_log('GESTALO: no se pudo cargar el menu de alojamientos: ' . $e->getMessage());
        $mensajeDisponibles = 'No se pudieron cargar los alojamientos. Contacta al administrador.';
        $mensajeOcupados = $mensajeDisponibles;
    }

    return [
        'principal' => [$modulo['nombreModulo'], $icono, [
            ['Dashboard', [
                ['General', null, null, 'general'],
                ['Personal', null, null, 'personal'],
                ['Alojamiento', null, null, 'inmueble-alojamiento'],
                ['Inventario', null, null, 'inventario'],
            ], null, 'dashboard', 'M3 3h8v8H3zm10 0h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z'],
            ['Mis propiedades', [
                ['Disponibles', $listas['disponibles'], null, 'disponibles', 'M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20zm-1 14 6-6-1.4-1.4-4.6 4.6-2.6-2.6L7 12z', $mensajeDisponibles, 'gestalo-disponibles'],
                ['Ocupados', $listas['ocupados'], null, 'ocupados', 'M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20zM7 11v2h10v-2z', $mensajeOcupados, 'gestalo-ocupados'],
            ], null, 'alojamiento', $iconoPropiedades],
            ['Mi personal', [
                ['Tareas', [
                    ['Asignar', null, null, 'asignar'],
                    ['Anular', null, null, 'anular'],
                ], null, 'tareas', 'M5 3h14v18H5zm3 4v2h2V7zm4 0v2h4V7zm-4 4v2h2v-2zm4 0v2h4v-2zm-4 4v2h2v-2zm4 0v2h4v-2z'],
                ['Vacaciones', [
                    ['Asignar', null, null, 'asignar'],
                    ['Anular', null, null, 'anular'],
                ], null, 'vacaciones', 'M11 3h2v2h-2zM3 11h2v2H3zm16 0h2v2h-2zM5 5l1.4-1.4 1.4 1.4L6.4 6.4zm12.6 0L19 3.6 20.4 5 19 6.4zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm-1 12h2v2h-2zM5 19l1.4-1.4L7.8 19l-1.4 1.4zm12.6 0 1.4-1.4 1.4 1.4-1.4 1.4z'],
            ], null, 'personal-limpieza', $iconoPersonal],
            ['Inventario', [
                ['Entrada', null, null, 'entrada'],
                ['Salida', null, null, 'salida'],
                ['Situación', null, null, 'actual'],
            ], null, 'kardex', $iconoInventario],
        ], null, 'modulo-' . (int) $modulo['idModulo']],
        'configuracion' => [$modulo['nombreModulo'], [
            ['Mis propiedades', [
                ['Inmuebles', [
                    ['Crear', null, null, 'crear'],
                    ['Habilitar/Inhabilitar', null, null, 'habilitar'],
                    ['Actualizar datos', null, null, 'actualizar'],
                    ['Eliminar', null, null, 'eliminar'],
                ], null, 'inmuebles', $iconoPropiedades],
                ['Alojamientos', [
                    ['Crear', null, null, 'crear'],
                    ['Habilitar/Inhabilitar', null, null, 'habilitar'],
                    ['Actualizar datos', null, null, 'actualizar'],
                    ['Eliminar', null, null, 'eliminar'],
                ], null, 'alojamientos', $icono],
            ], null, 'mis-propiedades', $iconoPropiedades],
            ['Mi Personal', [
                ['Crear', null, null, 'crear'],
                ['Habilitar/Inhabilitar', null, null, 'habilitar'],
                ['Actualizar datos', null, null, 'actualizar'],
                ['Eliminar', null, null, 'eliminar'],
            ], null, 'personal', $iconoPersonal],
            ['Mis huéspedes', [
                ['Crear', null, null, 'crear'],
                ['Actualizar datos', null, null, 'actualizar'],
                ['Vetar/Permitir', null, null, 'vetar'],
                ['Eliminar', null, null, 'eliminar'],
            ], null, 'huesped', 'M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8zM4 20c0-4 3.6-7 8-7s8 3 8 7v1H4z'],
            ['Inventario', [
                ['Crear categoría', null, null, 'crear-categoria'],
                ['Editar categoría', null, null, 'eliminar-categoria'],
                ['Mover entre categorías', null, null, 'mover-inventario'],
            ], null, 'kardex', $iconoInventario],
            ['Exportar datos', null, null, 'exportar-datos', 'M11 3h2v9l3-3 1.4 1.4L12 16l-5.4-5.6L8 9l3 3zM4 15h2v4h12v-4h2v6H4z'],
        ], null, 'config-gestalo', $icono],
    ];
};
