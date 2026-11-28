<?php

function empresaModulo(string $nombreModulo): array
{
    $parametro = $_GET['empresa'] ?? $_POST['empresa'] ?? $_SESSION['empresa_id'];
    $idEmpresa = filter_var($parametro, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($nombreModulo !== 'GESTALO' || $idEmpresa !== 1
        || (isset($_GET['empresa'], $_POST['empresa']) && $_GET['empresa'] !== $_POST['empresa'])) {
        http_response_code(403);
        exit('No tienes acceso a esta empresa o módulo.');
    }
    return ['id_empresa' => $idEmpresa];
}
