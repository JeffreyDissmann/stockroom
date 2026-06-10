<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Services\Maintenance\MaintenancePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->presenter = app(MaintenancePresenter::class);
});

describe('schedule summaries (en)', function () {
    // The app's default locale is de — pin these to English explicitly.
    beforeEach(fn () => app()->setLocale('en'));

    it('renders each rule shape', function (MaintenanceTask $task, string $expected) {
        expect($this->presenter->scheduleSummary($task))->toBe($expected);
    })->with([
        'interval plural' => [
            fn () => MaintenanceTask::factory()->interval(6, MaintenanceIntervalUnit::Months)->create(),
            'Every 6 months after completion',
        ],
        'interval singular' => [
            fn () => MaintenanceTask::factory()->interval(1, MaintenanceIntervalUnit::Weeks)->create(),
            'Every week after completion',
        ],
        'calendar every' => [
            fn () => MaintenanceTask::factory()->calendar('FREQ=MONTHLY;INTERVAL=3')->create(),
            'Every 3 months (fixed)',
        ],
        'yearly on a date' => [
            fn () => MaintenanceTask::factory()->calendar('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1')->create(),
            'Yearly on 1 April',
        ],
        'nth weekday yearly' => [
            fn () => MaintenanceTask::factory()->calendar('FREQ=YEARLY;BYMONTH=3;BYDAY=1SU')->create(),
            'Every first Sunday in March',
        ],
        'last weekday monthly' => [
            fn () => MaintenanceTask::factory()->calendar('FREQ=MONTHLY;BYDAY=-1FR')->create(),
            'Every last Friday of the month',
        ],
        'custom rule' => [
            fn () => MaintenanceTask::factory()->calendar('FREQ=WEEKLY;BYDAY=MO,WE,FR')->create(),
            'Custom schedule',
        ],
        'one-off' => [
            fn () => MaintenanceTask::factory()->oneOff()->dueSoon()->create(),
            'Once',
        ],
        'forecast' => [
            fn () => MaintenanceTask::factory()->forecast()->dueSoon()->create(),
            'Predicted from battery level',
        ],
    ]);

    it('renders German summaries under the de locale', function () {
        app()->setLocale('de');

        $interval = MaintenanceTask::factory()->interval(6, MaintenanceIntervalUnit::Months)->create();
        $nth = MaintenanceTask::factory()->calendar('FREQ=YEARLY;BYMONTH=3;BYDAY=1SU')->create();

        expect($this->presenter->scheduleSummary($interval))->toBe('Alle 6 Monate nach Erledigung')
            ->and($this->presenter->scheduleSummary($nth))->toBe('Jeden ersten Sonntag im März');
    });
});

describe('task rows', function () {
    it('serialises the dialog re-hydration fields and server-computed due state', function () {
        app()->setLocale('en');
        $task = MaintenanceTask::factory()->calendar('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1')->dueSoon(3)->create([
            'reminder_lead_days' => 7,
        ]);

        $row = $this->presenter->presentTask($task);

        expect($row)
            ->schedule_type->toBe('calendar')
            ->next_due_at->toBe(today()->addDays(3)->toDateString())
            ->due_in_days->toBe(3)
            ->due_label->toBe('Due in 3 days')
            ->is_overdue->toBeFalse()
            ->is_due_soon->toBeTrue()
            ->can_skip->toBeTrue()
            ->schedule_preset->toBe(['preset' => 'yearly_on', 'month' => 4, 'day' => 1]);
    });

    it('marks custom rules with a null preset', function () {
        $task = MaintenanceTask::factory()->calendar('FREQ=WEEKLY;BYDAY=MO,WE,FR')->create();

        expect($this->presenter->presentTask($task)['schedule_preset'])->toBeNull();
    });
});

describe('entry rows', function () {
    it('serialises performer and task names with null fallbacks', function () {
        $user = User::factory()->create(['name' => 'Jeff']);
        $task = MaintenanceTask::factory()->create(['title' => 'Descale']);
        $bound = MaintenanceEntry::factory()->forTask($task)->create(['performed_by' => $user->id]);
        $adHoc = MaintenanceEntry::factory()->create(['performed_by' => null]);

        expect($this->presenter->presentEntry($bound->load(['performer', 'task'])))
            ->performed_by_name->toBe('Jeff')
            ->task_title->toBe('Descale');

        expect($this->presenter->presentEntry($adHoc->load(['performer', 'task'])))
            ->performed_by_name->toBeNull()
            ->task_title->toBeNull();
    });
});
