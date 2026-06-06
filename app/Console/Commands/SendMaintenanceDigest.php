<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\MaintenanceDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maintenance:send-digest')]
#[Description('Email opted-in users a digest of maintenance that is overdue or due soon')]
class SendMaintenanceDigest extends Command
{
    public function handle(): int
    {
        // Indexed prefilter using the WIDEST reminder window, then the
        // exact per-task test in PHP (the per-row window arithmetic isn't
        // portable SQL) — same two-step the dashboard partition uses.
        $maxLead = (int) MaintenanceTask::query()->active()->max('reminder_lead_days');

        $tasks = MaintenanceTask::query()
            ->active()
            ->whereNotNull('next_due_at')
            ->whereDate('next_due_at', '<=', today()->addDays($maxLead))
            ->with('item')
            ->orderBy('next_due_at')
            ->get()
            ->filter(fn (MaintenanceTask $task): bool => $task->needsAttention());

        // Anti-spam: an all-clear household gets no email at all. Overdue
        // tasks keep reappearing daily until done — that nag is the point.
        if ($tasks->isEmpty()) {
            $this->info('No maintenance needs attention — no digest sent.');

            return self::SUCCESS;
        }

        $overdue = $tasks->filter(fn (MaintenanceTask $task): bool => $task->isOverdue())->values();
        $dueSoon = $tasks->reject(fn (MaintenanceTask $task): bool => $task->isOverdue())->values();

        $recipients = User::query()->where('maintenance_digest_opt_in', true)->get();

        foreach ($recipients as $user) {
            $user->notify(new MaintenanceDigest($overdue, $dueSoon));
        }

        $this->info(sprintf(
            'Digest sent to %d user(s): %d overdue, %d due soon.',
            $recipients->count(),
            $overdue->count(),
            $dueSoon->count(),
        ));

        return self::SUCCESS;
    }
}
