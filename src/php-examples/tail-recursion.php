<?php

declare(strict_types=1);

// Tail recursion example in PHP 8.3
function factorial(int $number, int $accumulator = 1): int
{
    if ($number <= 1) {
        return $accumulator;
    }

    return factorial($number - 1, $number * $accumulator);
}

echo factorial(5), "\n";

// Implementing fibonacci function using tail recursion and accumulator pattern in PHP 8.3

function fibonacci(int $n, int $a = 0, int $b = 1): int
{
    if ($n < 0) {
        throw new InvalidArgumentException('Fibonacci index must be a non-negative integer.');
    }

    if ($n === 0) {
        return $a;
    }

    if ($n === 1) {
        return $b;
    }

    return fibonacci($n - 1, $b, $a + $b);
}


echo fibonacci(0), "\n"; // Output: 0
echo fibonacci(5), "\n"; // Output: 5
echo fibonacci(10), "\n"; // Output: 55
