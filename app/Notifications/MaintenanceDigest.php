<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MaintenanceTask;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The daily maintenance digest: one email listing everything that needs
 * attention (overdue first, then due-soon), linking to the /maintenance
 * overview. Deliberately NOT queued — the household has a handful of
 * recipients and sending synchronously from the scheduled command avoids
 * depending on a queue worker running on the NAS.
 *
 * Renders in the recipient's locale via User::preferredLocale().
 */
class MaintenanceDigest extends Notification
{
    /**
     * @param  Collection<int, MaintenanceTask>  $overdue
     * @param  Collection<int, MaintenanceTask>  $dueSoon
     */
    public function __construct(
        public readonly Collection $overdue,
        public readonly Collection $dueSoon,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->overdue->count() + $this->dueSoon->count();

        $message = (new MailMessage)
            ->subject(trans_choice('maintenance.digest.subject', $total))
            ->line(__('maintenance.digest.intro'));

        if ($this->overdue->isNotEmpty()) {
            $message->line('**'.__('maintenance.digest.overdue_heading').'**');
            foreach ($this->overdue as $task) {
                $message->line($this->taskLine($task));
            }
        }

        if ($this->dueSoon->isNotEmpty()) {
            $message->line('**'.__('maintenance.digest.due_soon_heading').'**');
            foreach ($this->dueSoon as $task) {
                $message->line($this->taskLine($task));
            }
        }

        return $message->action(__('maintenance.digest.action'), route('maintenance'));
    }

    /**
     * One markdown bullet per task: "Change batteries — [Smoke detector]
     * (3 days overdue)", with the item name linking to its page. The due
     * wording reuses the badge translations so email and UI never phrase
     * the same state differently.
     */
    private function taskLine(MaintenanceTask $task): string
    {
        $days = $task->dueInDays();

        $due = match (true) {
            $days === null => __('maintenance.due.none'),
            $days < 0 => trans_choice('maintenance.due.overdue', -$days),
            $days === 0 => __('maintenance.due.today'),
            default => trans_choice('maintenance.due.in_days', $days),
        };

        return __('maintenance.digest.task_line', [
            'task' => $task->title,
            // Markdown link assembled here, not in the translation —
            // translators only ever see the :item placeholder.
            'item' => sprintf('[%s](%s)', $task->item->name, route('items.show', $task->item)),
            'due' => $due,
        ]);
    }
}
