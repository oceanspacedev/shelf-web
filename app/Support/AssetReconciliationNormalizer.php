<?php

namespace App\Support;

use Illuminate\Support\Str;

final class AssetReconciliationNormalizer
{
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    public static function key(mixed $value): ?string
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value);

        return $value === '' ? null : $value;
    }

    public static function identity(mixed $value): ?string
    {
        $value = self::key($value);

        return in_array($value, [null, '0', 'null', 'na', 'n/a'], true) ? null : $value;
    }

    public static function quantity(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public static function integerQuantity(mixed $value): ?int
    {
        $quantity = self::quantity($value);

        if ($quantity === null || abs($quantity - round($quantity)) > 0.00001) {
            return null;
        }

        return (int) round($quantity);
    }
}
