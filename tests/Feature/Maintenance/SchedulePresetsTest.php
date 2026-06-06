<?php

declare(strict_types=1);

use App\Services\Maintenance\SchedulePresets;

beforeEach(function () {
    $this->presets = new SchedulePresets;
});

describe('payload → RRULE', function () {
    it('builds the curated presets', function (array $payload, string $expected) {
        expect($this->presets->toRrule($payload))->toBe($expected);
    })->with([
        'every month' => [['preset' => 'every', 'interval' => 1, 'unit' => 'months'], 'FREQ=MONTHLY'],
        'every 6 weeks' => [['preset' => 'every', 'interval' => 6, 'unit' => 'weeks'], 'FREQ=WEEKLY;INTERVAL=6'],
        'every 2 years' => [['preset' => 'every', 'interval' => 2, 'unit' => 'years'], 'FREQ=YEARLY;INTERVAL=2'],
        'every 10 days' => [['preset' => 'every', 'interval' => 10, 'unit' => 'days'], 'FREQ=DAILY;INTERVAL=10'],
        'yearly on Apr 1' => [['preset' => 'yearly_on', 'month' => 4, 'day' => 1], 'FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1'],
        'first Sunday in March' => [['preset' => 'nth_weekday', 'ordinal' => 1, 'weekday' => 'SU', 'month' => 3], 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU'],
        'last Friday monthly' => [['preset' => 'nth_weekday', 'ordinal' => -1, 'weekday' => 'FR', 'month' => null], 'FREQ=MONTHLY;BYDAY=-1FR'],
    ]);

    it('rejects malformed payloads', function (array $payload) {
        expect(fn () => $this->presets->toRrule($payload))->toThrow(InvalidArgumentException::class);
    })->with([
        'unknown preset' => [['preset' => 'cron']],
        'zero interval' => [['preset' => 'every', 'interval' => 0, 'unit' => 'days']],
        'bad unit' => [['preset' => 'every', 'interval' => 1, 'unit' => 'fortnights']],
        'month out of range' => [['preset' => 'yearly_on', 'month' => 13, 'day' => 1]],
        'day out of range' => [['preset' => 'yearly_on', 'month' => 1, 'day' => 32]],
        'ordinal zero' => [['preset' => 'nth_weekday', 'ordinal' => 0, 'weekday' => 'MO', 'month' => null]],
        'ordinal five' => [['preset' => 'nth_weekday', 'ordinal' => 5, 'weekday' => 'MO', 'month' => null]],
        'bad weekday' => [['preset' => 'nth_weekday', 'ordinal' => 1, 'weekday' => 'XX', 'month' => null]],
    ]);
});

describe('RRULE → payload', function () {
    it('round-trips every preset shape', function (array $payload) {
        $rrule = $this->presets->toRrule($payload);

        expect($this->presets->toPayload($rrule))->toBe($payload);
    })->with([
        'every month' => [['preset' => 'every', 'interval' => 1, 'unit' => 'months']],
        'every 6 weeks' => [['preset' => 'every', 'interval' => 6, 'unit' => 'weeks']],
        'yearly on Oct 1' => [['preset' => 'yearly_on', 'month' => 10, 'day' => 1]],
        'first Sunday in March' => [['preset' => 'nth_weekday', 'ordinal' => 1, 'weekday' => 'SU', 'month' => 3]],
        'last Friday monthly' => [['preset' => 'nth_weekday', 'ordinal' => -1, 'weekday' => 'FR', 'month' => null]],
    ]);

    it('returns null for rules beyond the presets', function (string $rrule) {
        expect($this->presets->toPayload($rrule))->toBeNull();
    })->with([
        'multiple weekdays' => ['FREQ=WEEKLY;BYDAY=MO,WE,FR'],
        'counted rule' => ['FREQ=MONTHLY;COUNT=10'],
        'until rule' => ['FREQ=MONTHLY;UNTIL=20300101T000000Z'],
        'bysetpos' => ['FREQ=MONTHLY;BYDAY=MO;BYSETPOS=2'],
        'interval + byday' => ['FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO'],
        'garbage' => ['not an rrule'],
    ]);
});

describe('validation', function () {
    it('accepts parseable rules and rejects garbage', function () {
        expect($this->presets->isValid('FREQ=YEARLY;BYMONTH=3;BYDAY=1SU'))->toBeTrue()
            ->and($this->presets->isValid('FREQ=NEVERLY'))->toBeFalse()
            ->and($this->presets->isValid(''))->toBeFalse();
    });
});
