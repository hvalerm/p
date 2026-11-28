<?php
require_once __DIR__ . '/../BaseDatos/db.php';

function empresaModulo(string $nombreModulo): array
{
    $get = $_GET['empresa'] ?? null;
    $post = $_POST['empresa'] ?? null;
    if (($get !== null && !is_string($get)) || ($post !== null && !is_string($post))
        || ($get !== null && $post !== null && $get !== $post)) {
        http_response_code(400);
        exit('Empresa no válida.');
    }
    $idEmpresa = filter_var($get ?? $post ?? $_SESSION['empresa_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idEmpresa === false) {
        http_response_code(400);
        exit('Empresa no válida.');
    }
    try {
        $empresa = empresaUnicaCuenta(getPDO(), (int) $_SESSION['usuario_id']);
        if ((int) $empresa['id_empresa'] !== $idEmpresa) {
            http_response_code(403);
            exit('No tienes acceso a esta empresa.');
        }
        $stmt = getPDO()->prepare(
            'SELECT m.nombreModulo FROM empresas_modulos em
             INNER JOIN modulos m ON m.idModulo = em.idModulo
             WHERE em.idEmpresa = ? AND em.habilitado = 1 AND DATE(em.licenciaExpira) >= CURRENT_DATE'
        );
        $stmt->execute([$idEmpresa]);
        if (!in_array($nombreModulo, $stmt->fetchAll(PDO::FETCH_COLUMN), true)) {
            http_response_code(403);
            exit('El módulo no está habilitado o su licencia ha vencido.');
        }
        return $empresa;
    } catch (DomainException $e) {
        http_response_code(403);
        exit('No tienes acceso a esta empresa.');
    } catch (PDOException | RuntimeException $e) {
        error_log('No se pudo validar la empresa del módulo: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar el módulo. Inténtalo más tarde.');
    }
}
