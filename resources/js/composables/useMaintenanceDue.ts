import { trans, transChoice } from '@/composables/useTranslations';
import type { MaintenanceTaskRow } from '@/types';

/**
 * Shared due-state presentation for maintenance tasks — the item section,
 * the global maintenance page and the dashboard card must all agree on
 * what the badge says and when it lights up.
 */
export function useMaintenanceDue() {
    function dueBadge(task: MaintenanceTaskRow): string {
        if (task.due_in_days === null) return trans('maintenance.due.none');
        if (task.due_in_days < 0) return transChoice('maintenance.due.overdue', -task.due_in_days);
        if (task.due_in_days === 0) return trans('maintenance.due.today');
        return transChoice('maintenance.due.in_days', task.due_in_days);
    }

    // "Due soon" = inside the task's own reminder window, mirroring the
    // daily digest's inclusion test.
    function isDueSoon(task: MaintenanceTaskRow): boolean {
        return task.due_in_days !== null && task.due_in_days >= 0 && task.due_in_days <= task.reminder_lead_days;
    }

    return { dueBadge, isDueSoon };
}
