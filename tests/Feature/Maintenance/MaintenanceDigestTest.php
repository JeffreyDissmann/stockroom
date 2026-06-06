<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\MaintenanceDigest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends the digest to opted-in users with the overdue/due-soon partition', function () {
    Notification::fake();

    $optedIn = User::factory()->create();
    $optedOut = User::factory()->create(['maintenance_digest_opt_in' => false]);

    $overdue = MaintenanceTask::factory()->overdue(3)->create();
    $dueSoon = MaintenanceTask::factory()->dueSoon(2)->create(['reminder_lead_days' => 7]);
    MaintenanceTask::factory()->dueSoon(60)->create(['reminder_lead_days' => 7]); // future: excluded
    MaintenanceTask::factory()->overdue(5)->inactive()->create();                 // archived: excluded

    $this->artisan('maintenance:send-digest')->assertSuccessful();

    Notification::assertSentTo($optedIn, MaintenanceDigest::class, function (MaintenanceDigest $digest) use ($overdue, $dueSoon): bool {
        return $digest->overdue->pluck('id')->all() === [$overdue->id]
            && $digest->dueSoon->pluck('id')->all() === [$dueSoon->id];
    });
    Notification::assertNotSentTo($optedOut, MaintenanceDigest::class);
});

it('sends nothing when no task needs attention', function () {
    Notification::fake();

    User::factory()->create();
    MaintenanceTask::factory()->dueSoon(60)->create(['reminder_lead_days' => 7]);

    $this->artisan('maintenance:send-digest')->assertSuccessful();

    Notification::assertNothingSent();
});

it('respects each task\'s own reminder lead, not the widest one', function () {
    Notification::fake();

    $user = User::factory()->create();
    // Same due date, different windows: only the long-lead task qualifies.
    $longLead = MaintenanceTask::factory()->dueSoon(10)->create(['reminder_lead_days' => 30]);
    MaintenanceTask::factory()->dueSoon(10)->create(['reminder_lead_days' => 3]);

    $this->artisan('maintenance:send-digest')->assertSuccessful();

    Notification::assertSentTo($user, MaintenanceDigest::class, function (MaintenanceDigest $digest) use ($longLead): bool {
        return $digest->dueSoon->pluck('id')->all() === [$longLead->id]
            && $digest->overdue->isEmpty();
    });
});

it('renders subject, sections, task lines and the action link', function () {
    $item = Item::factory()->create(['name' => 'Smoke detector']);
    $overdue = MaintenanceTask::factory()->for($item)->overdue(3)->create(['title' => 'Change batteries']);
    $dueSoon = MaintenanceTask::factory()->for($item)->dueSoon(2)->create(['title' => 'Test alarm', 'reminder_lead_days' => 7]);
    $user = User::factory()->create(['locale' => 'en']);

    // toMail() renders in the CURRENT app locale (NotificationSender does
    // the per-recipient switch in real sends); pin to en — the app's
    // default test locale is de.
    app()->setLocale('en');
    $mail = (new MaintenanceDigest(collect([$overdue]), collect([$dueSoon])))->toMail($user);

    $itemLink = sprintf('[Smoke detector](%s)', route('items.show', $item));

    expect($mail->subject)->toBe('Stockroom: 2 maintenance tasks need attention')
        ->and($mail->actionUrl)->toBe(route('maintenance'))
        ->and(implode("\n", $mail->introLines))
        ->toContain("Change batteries — {$itemLink} (3 days overdue)")
        ->toContain("Test alarm — {$itemLink} (Due in 2 days)")
        ->toContain('**Overdue**')
        ->toContain('**Due soon**');
});

it('renders in the recipient\'s preferred locale', function () {
    $item = Item::factory()->create(['name' => 'Rauchmelder']);
    $overdue = MaintenanceTask::factory()->for($item)->overdue(1)->create(['title' => 'Batterien wechseln']);
    $user = User::factory()->create(['locale' => 'de']);

    expect($user->preferredLocale())->toBe('de');

    // NotificationSender switches the app locale to preferredLocale()
    // before rendering; emulate that to assert the German output.
    app()->setLocale('de');
    $mail = (new MaintenanceDigest(collect([$overdue]), collect()))->toMail($user);

    expect($mail->subject)->toBe('Stockroom: 1 Wartungsaufgabe wartet')
        ->and(implode("\n", $mail->introLines))->toContain('1 Tag überfällig');
});

it('is scheduled daily at 07:00', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'maintenance:send-digest'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 7 * * *');
});
