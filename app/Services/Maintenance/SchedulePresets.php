<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use InvalidArgumentException;
use Recurr\Exception\InvalidRRule;
use Recurr\Rule;

/**
 * Maps the curated schedule-builder presets the UI exposes to RFC 5545
 * RRULE strings and back. The UI never edits raw RRULEs — it edits one of
 * three preset shapes; storing the result AS an RRULE keeps the schema open
 * for richer rules later without a migration.
 *
 * Preset payloads (validated by the FormRequest before they get here):
 *  - every:        ['preset' => 'every', 'interval' => int, 'unit' => 'days|weeks|months|years']
 *  - yearly_on:    ['preset' => 'yearly_on', 'month' => 1-12, 'day' => 1-31]
 *  - nth_weekday:  ['preset' => 'nth_weekday', 'ordinal' => 1|2|3|4|-1,
 *                   'weekday' => 'MO'..'SU', 'month' => null|1-12]
 *                  (month null = every month, set = yearly in that month)
 *
 * toPayload() is the reverse for the edit dialog; it returns null for any
 * RRULE the presets cannot express, which the UI renders read-only.
 */
class SchedulePresets
{
    private const array UNIT_FREQUENCIES = [
        'days' => 'DAILY',
        'weeks' => 'WEEKLY',
        'months' => 'MONTHLY',
        'years' => 'YEARLY',
    ];

    private const array WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toRrule(array $payload): string
    {
        return match ($payload['preset'] ?? null) {
            'every' => $this->everyToRrule($payload),
            'yearly_on' => $this->yearlyOnToRrule($payload),
            'nth_weekday' => $this->nthWeekdayToRrule($payload),
            default => throw new InvalidArgumentException('Unknown schedule preset: '.json_encode($payload['preset'] ?? null)),
        };
    }

    /**
     * Re-hydrate the builder payload from a stored RRULE, or null when the
     * rule is beyond what the presets can express.
     *
     * @return array<string, mixed>|null
     */
    public function toPayload(string $rrule): ?array
    {
        try {
            $rule = new Rule($rrule);
        } catch (InvalidRRule) {
            return null;
        }

        // Anything beyond FREQ/INTERVAL/BYMONTH/BYMONTHDAY/BYDAY is out of
        // preset territory.
        if ($rule->getCount() !== null
            || $rule->getUntil() !== null
            || $rule->getBySetPosition()
            || $rule->getByHour()
            || $rule->getByWeekNumber()
            || $rule->getByYearDay()) {
            return null;
        }

        $frequency = $rule->getFreqAsText();
        $interval = $rule->getInterval();
        $byMonth = $rule->getByMonth() ?: [];
        $byMonthDay = $rule->getByMonthDay() ?: [];
        $byDay = $rule->getByDay() ?: [];

        // every N days/weeks/months/years — bare frequency, no BY* parts.
        if (! $byMonth && ! $byMonthDay && ! $byDay) {
            $unit = array_search($frequency, self::UNIT_FREQUENCIES, true);

            return $unit === false ? null : [
                'preset' => 'every',
                'interval' => $interval,
                'unit' => $unit,
            ];
        }

        // yearly on a fixed date.
        if ($frequency === 'YEARLY' && $interval === 1
            && count($byMonth) === 1 && count($byMonthDay) === 1 && ! $byDay) {
            return [
                'preset' => 'yearly_on',
                'month' => (int) $byMonth[0],
                'day' => (int) $byMonthDay[0],
            ];
        }

        // Nth weekday, monthly or yearly-in-a-month.
        if (count($byDay) === 1 && ! $byMonthDay && $interval === 1
            && preg_match('/^(-?[1-4])(MO|TU|WE|TH|FR|SA|SU)$/', (string) $byDay[0], $matches)) {
            if ($frequency === 'MONTHLY' && ! $byMonth) {
                return [
                    'preset' => 'nth_weekday',
                    'ordinal' => (int) $matches[1],
                    'weekday' => $matches[2],
                    'month' => null,
                ];
            }

            if ($frequency === 'YEARLY' && count($byMonth) === 1) {
                return [
                    'preset' => 'nth_weekday',
                    'ordinal' => (int) $matches[1],
                    'weekday' => $matches[2],
                    'month' => (int) $byMonth[0],
                ];
            }
        }

        return null;
    }

    /**
     * Whether recurr can parse the rule at all — the FormRequest's
     * validation hook for the rrule column.
     */
    public function isValid(string $rrule): bool
    {
        try {
            // recurr parses an empty/FREQ-less string without complaint;
            // a rule that recurs needs a frequency, so require one.
            return (new Rule($rrule))->getFreq() !== null;
        } catch (InvalidRRule) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function everyToRrule(array $payload): string
    {
        $frequency = self::UNIT_FREQUENCIES[$payload['unit'] ?? null]
            ?? throw new InvalidArgumentException('Unknown interval unit: '.json_encode($payload['unit'] ?? null));
        $interval = $this->positiveInt($payload, 'interval');

        return $interval === 1 ? "FREQ={$frequency}" : "FREQ={$frequency};INTERVAL={$interval}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function yearlyOnToRrule(array $payload): string
    {
        $month = $this->intInRange($payload, 'month', 1, 12);
        $day = $this->intInRange($payload, 'day', 1, 31);

        return "FREQ=YEARLY;BYMONTH={$month};BYMONTHDAY={$day}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nthWeekdayToRrule(array $payload): string
    {
        $ordinal = (int) ($payload['ordinal'] ?? 0);
        if ($ordinal === 0 || $ordinal > 4 || $ordinal < -1) {
            throw new InvalidArgumentException('Ordinal must be 1-4 or -1 (last).');
        }

        $weekday = $payload['weekday'] ?? null;
        if (! in_array($weekday, self::WEEKDAYS, true)) {
            throw new InvalidArgumentException('Unknown weekday: '.json_encode($weekday));
        }

        if (($payload['month'] ?? null) === null) {
            return "FREQ=MONTHLY;BYDAY={$ordinal}{$weekday}";
        }

        $month = $this->intInRange($payload, 'month', 1, 12);

        return "FREQ=YEARLY;BYMONTH={$month};BYDAY={$ordinal}{$weekday}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function positiveInt(array $payload, string $key): int
    {
        $value = (int) ($payload[$key] ?? 0);
        if ($value < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function intInRange(array $payload, string $key, int $min, int $max): int
    {
        $value = (int) ($payload[$key] ?? 0);
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$key} must be between {$min} and {$max}.");
        }

        return $value;
    }
}
