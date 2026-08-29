<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\Proposals\PhotoReviewRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Reviews the photos of items nobody has reviewed yet, and records proposals.
 *
 * Runs the work in this process rather than dispatching it, which is deliberate.
 * The production queue worker runs with `--timeout=120`, and a vision call costs
 * roughly fifteen seconds per photo: an item with a handful of photos would be
 * killed mid-flight and retried three times, burning the same inference over and
 * over. The scheduler is a separate container from the queue worker, so a slow
 * run here delays neither user-facing jobs nor the other scheduled tasks.
 */
class ReviewItemPhotos extends Command
{
    protected $signature = 'items:review-photos
                            {--limit=20 : How many items to review in this run}
                            {--item= : Review one item by id, ignoring the queue guard}
                            {--force : Run even while other queued work is pending}';

    protected $description = 'Look at unreviewed item photos and record catalogue proposals';

    public function handle(PhotoReviewRunner $runner): int
    {
        if (! config('ai.enabled')) {
            $this->components->warn('AI is disabled; nothing to do.');

            return self::SUCCESS;
        }

        if ($id = $this->option('item')) {
            return $this->reviewOne($runner, (int) $id);
        }

        // Vision inference is the heaviest thing this app asks of its hardware.
        // If the queue still has work, something a person is waiting on is more
        // deserving of the GPU than a background tidy-up.
        if (! $this->option('force') && ($pending = $this->pendingJobs()) > 0) {
            $this->components->info("Skipped: {$pending} queued job(s) still pending.");

            return self::SUCCESS;
        }

        $items = Item::awaitingPhotoReview()
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($items->isEmpty()) {
            $this->components->info('Every photo has been reviewed.');

            return self::SUCCESS;
        }

        $proposed = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                $count = $runner->review($item)->count();
                $proposed += $count;
                $this->components->twoColumnDetail($item->name, $count === 0 ? 'nothing to add' : "{$count} proposal(s)");
            } catch (Throwable $e) {
                $failed++;
                // One unreadable photo or a model hiccup must not end the run —
                // the remaining items are still worth reviewing tonight.
                $this->components->twoColumnDetail($item->name, '<fg=red>failed</>');
                report($e);
            }
        }

        $this->newLine();
        $this->components->info("Reviewed {$items->count()} item(s), recorded {$proposed} proposal(s).");

        if ($failed > 0) {
            $this->components->warn("{$failed} item(s) failed; they stay in the queue for the next run.");
        }

        return self::SUCCESS;
    }

    private function reviewOne(PhotoReviewRunner $runner, int $id): int
    {
        $item = Item::find($id);

        if (! $item) {
            $this->components->error("No item with id {$id}.");

            return self::FAILURE;
        }

        $count = $runner->review($item)->count();
        $this->components->info("Recorded {$count} proposal(s) for \"{$item->name}\".");

        return self::SUCCESS;
    }

    /**
     * Queued work still waiting to be picked up.
     *
     * Asks the queue rather than reading the jobs table, so the guard keeps
     * working if the driver is ever changed. On `sync` there is no queue to be
     * busy and this is always zero, which is correct: the work runs inline.
     */
    private function pendingJobs(): int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            // A driver that cannot be counted is not a reason to refuse the run.
            return 0;
        }
    }
}
