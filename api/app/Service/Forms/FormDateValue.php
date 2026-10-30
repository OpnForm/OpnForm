<?php

namespace App\Service\Forms;

use DateTimeImmutable;
use DateTimeZone;

class FormDateValue
{
    public static function comparisonValue(mixed $value, bool $withTime): ?float
    {
        $timestamp = self::timestamp($value);
        if ($timestamp === null || $withTime) {
            return $timestamp;
        }

        // DateInput includes a time/offset even for calendar-only inputs.
        return self::timestamp(substr($value, 0, 10));
    }

    /** Strict ISO dates only; timezone-free values use UTC in both runtimes. */
    public static function timestamp(mixed $value): ?float
    {
        if (! is_string($value) || ! preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.(\d{1,6}))?)?(Z|[+-]\d{2}:\d{2})?)?\z/', $value, $parts)) {
            return null;
        }

        [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        $hour = (int) ($parts[4] ?? 0);
        $minute = (int) ($parts[5] ?? 0);
        $second = (int) ($parts[6] ?? 0);
        $zone = $parts[8] ?? 'Z';
        if (! checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }
        $offset = 0;
        if ($zone !== 'Z') {
            $zoneHour = (int) substr($zone, 1, 2);
            $zoneMinute = (int) substr($zone, 4, 2);
            if ($zoneHour > 23 || $zoneMinute > 59) {
                return null;
            }
            $offset = ($zoneHour * 60 + $zoneMinute) * ($zone[0] === '-' ? -1 : 1);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second), new DateTimeZone('UTC'));
        $milliseconds = (int) str_pad(substr($parts[7] ?? '', 0, 3), 3, '0');

        return $date === false ? null : ($date->getTimestamp() - $offset * 60) * 1000.0 + $milliseconds;
    }
}
