<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Known battery cell types. The `battery_cycles.battery_type` column is a
 * free string (an unusual cell can still be recorded), so this enum is not
 * a cast — it's the curated list the UI picker offers and the API validates
 * as suggestions. The backing value IS the display string, so a stored
 * value reads naturally ("AA", "CR2032", "9V").
 */
enum BatteryType: string
{
    case AA = 'AA';
    case AAA = 'AAA';
    case AAAA = 'AAAA';
    case C = 'C';
    case D = 'D';
    case NineVolt = '9V';
    case CR2032 = 'CR2032';
    case CR2025 = 'CR2025';
    case CR2016 = 'CR2016';
    case CR123A = 'CR123A';
    case Cell18650 = '18650';
    case LiIon = 'Li-ion';

    /**
     * All known type values, for the UI picker and API suggestion list.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
