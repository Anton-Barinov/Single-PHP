<?php

declare(strict_types=1);

namespace App;

class Calculator
{
    public static function compute(string $op, float $a, float $b): float
    {
        return match ($op) {
            '+', 'add' => $a + $b,
            '-', 'sub' => $a - $b,
            '*', 'mul' => $a * $b,
            '/', 'div' => $b != 0 ? $a / $b : throw new \InvalidArgumentException("Division by zero"),
            default    => throw new \InvalidArgumentException("Unknown operation: {$op}"),
        };
    }
}
