<?php

namespace App\Support;

class NomenclaturaPublica
{
    public static function clientes(string $texto): string
    {
        // Normalize configurable interface copy, preserving links, emails and stored data.
        return preg_replace_callback('~https?://\S+|[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+|\b(solicitantes?)\b~iu', static function ($match) {
            if (empty($match[1])) return $match[0];
            $rotulo = str_ends_with(strtolower($match[1]), 's') ? 'clientes' : 'cliente';
            if ($match[1] === strtoupper($match[1])) return strtoupper($rotulo);
            return ctype_upper($match[1][0]) ? ucfirst($rotulo) : $rotulo;
        }, $texto);
    }
}
