<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function cents(mixed $value): int
    {
        $s = trim((string) ($value ?? '0'));
        if (! preg_match('/^\d{1,15}(?:\.\d{1,2})?$/D', $s)) {
            throw ValidationException::withMessages(['amount' => 'Tutar pozitif, en fazla iki ondalık basamaklı olmalı (örn. 1250.50).']);
        }
        [$whole, $fraction] = array_pad(explode('.', $s), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function signedCents(mixed $value): int
    {
        $s = (string) $value;

        return str_starts_with($s, '-') ? -self::cents(substr($s, 1)) : self::cents($s);
    }

    public static function format(mixed $value): string
    {
        $c = self::signedCents($value ?? '0');

        return ($c < 0 ? '-' : '').number_format(intdiv(abs($c), 100), 0, ',', '.').','.str_pad((string) (abs($c) % 100), 2, '0', STR_PAD_LEFT).' ₺';
    }
}
