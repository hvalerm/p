<?php
require_once __DIR__ . '/../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../../../util/Seguridad/modulos.php';
require_once __DIR__ . '/../../../util/BaseDatos/db.php';
require_once __DIR__ . '/util/db.php';
require_once __DIR__ . '/util/consultas.php';

requerirSesion();
$empresa = empresaModulo('GESTALO');
header('Cache-Control: no-store');
$h = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$idEmpresa = (int) $empresa['id_empresa'];
$idAlojamiento = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$alojamiento = null;
$reservas = [];
$hoy = '';
$error = '';

try {
    if (!$idAlojamiento) {
        http_response_code(400);
        $error = 'El alojamiento indicado no es válido.';
    } else {
        $pdo = getGestaloPDO();
        $alojamiento = gestaloBuscarAlojamiento($pdo, $idEmpresa, $idAlojamiento);
        if ($alojamiento === null) {
            http_response_code(404);
            $error = 'El alojamiento no existe o no está disponible para tu empresa.';
        } elseif (!in_array($alojamiento['zonaHoraria'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new UnexpectedValueException('Zona horaria del inmueble no valida.');
        } elseif (!in_array((string) $alojamiento['disponible'], ['0', '1'], true)) {
            throw new UnexpectedValueException('Disponibilidad de alojamiento no valida.');
        } else {
            $zona = new DateTimeZone($alojamiento['zonaHoraria']);
            $hoy = (new DateTimeImmutable('now', $zona))->format('Y-m-d');
            $reservas = gestaloReservasCalendario($pdo, $idEmpresa, $idAlojamiento, $zona);
        }
    }
} catch (PDOException | RuntimeException | UnexpectedValueException $e) {
    error_log('GESTALO: no se pudo cargar el calendario: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No se pudo cargar el calendario. Inténtalo más tarde.';
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Calendario | GESTALO</title>
    <link rel="stylesheet" href="calendario.css">
</head>
<body>
<main class="panel">
    <?php if ($error !== ''): ?>
        <h1>Calendario</h1>
        <p class="aviso error" role="alert"><?= $h($error) ?></p>
    <?php else: ?>
        <p class="seccion">GESTALO › Alojamiento</p>
        <h1><?= $h($alojamiento['nombreAlojamiento']) ?></h1>
        <p class="descripcion"><?= $h($alojamiento['nombreInmueble']) ?> ·
            <?= (string) $alojamiento['disponible'] === '1' ? 'Disponible' : 'Ocupado' ?></p>
        <h2>Calendario de reservas</h2>
        <p>Selecciona primero el check-in y después el check-out. Los días reservados aparecen en negrita.</p>
        <div id="calendario" class="calendario"
             data-hoy="<?= $h($hoy) ?>"
             data-reservas="<?= $h(json_encode($reservas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>">
            <div class="controles">
                <button type="button" id="mes-anterior" aria-label="Mes anterior">‹</button>
                <h3 id="mes-actual" aria-live="polite"></h3>
                <button type="button" id="mes-siguiente" aria-label="Mes siguiente">›</button>
            </div>
            <div class="semana" aria-hidden="true">
                <span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span>
            </div>
            <div class="dias" id="dias"></div>
            <p class="leyenda"><strong>Negrita: noche reservada</strong> · Normal: noche disponible</p>
            <p id="seleccion" class="seleccion" aria-live="polite">Selecciona la fecha de check-in.</p>
            <p id="mensaje" class="aviso error" role="alert" hidden></p>
            <button type="button" id="limpiar">Limpiar selección</button>
        </div>
        <p class="nota">La selección es informativa: todavía no se guarda ninguna reserva.</p>
        <noscript><p class="aviso error">Activa JavaScript para usar el calendario.</p></noscript>
        <script src="calendario.js" defer></script>
    <?php endif; ?>
</main>
</body>
</html>
