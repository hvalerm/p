<?php

function variableEntornoRequerida(string $nombre): string
{
    $valor = getenv($nombre);
    if ($valor === false || trim($valor) === '') {
        throw new RuntimeException('Falta configurar ' . $nombre . '.');
    }
    return $valor;
}
