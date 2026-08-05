<?php

namespace App\Support;

final class VehiclePlateNormalizer
{
    public static function display(mixed $value): ?string
    {
        $text = AssetReconciliationNormalizer::text($value);

        if ($text === null) {
            return null;
        }

        $upper = strtoupper($text);

        if (preg_match('/\b([A-Z]{1,2})\s*(\d{1,4})\s*([A-Z]{1,3})\b/', $upper, $matches) !== 1) {
            return null;
        }

        return trim($matches[1].' '.$matches[2].' '.$matches[3]);
    }

    public static function key(mixed $value): ?string
    {
        $display = self::display($value);

        return $display === null ? null : AssetReconciliationNormalizer::key($display);
    }

    public static function extractFromText(mixed $value): ?string
    {
        $text = AssetReconciliationNormalizer::text($value);

        if ($text === null) {
            return null;
        }

        if (preg_match('/\b([A-Z]{1,2}\s*\d{1,4}\s*[A-Z]{1,3})\b/i', $text, $matches) !== 1) {
            return null;
        }

        return self::display($matches[1]);
    }

    public static function chassis(mixed $engineOrChassis): ?string
    {
        $text = AssetReconciliationNormalizer::text($engineOrChassis);

        if ($text === null) {
            return null;
        }

        $parts = preg_split('/\s*\/\s*/', $text) ?: [];
        $chassis = AssetReconciliationNormalizer::text($parts[0] ?? null);

        if ($chassis === null) {
            return null;
        }

        return strtoupper(rtrim($chassis, '`'));
    }
}
