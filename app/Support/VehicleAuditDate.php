<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

final class VehicleAuditDate
{
    public static function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        if (is_numeric($value)) {
            $number = (float) $value;

            // Excel serial dates for operational vehicle docs fall in a sane modern range.
            if ($number >= 30000 && $number <= 80000) {
                try {
                    return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($number))->toDateString();
                } catch (Throwable) {
                    return null;
                }
            }
        }

        $text = AssetReconciliationNormalizer::text($value);

        if ($text === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($text)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
