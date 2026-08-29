<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Item;
use App\Services\Proposals\PhotoReviewRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the photo review on demand, for the button on the review queue.
 *
 * The scheduled command does the same work nightly and stands down when the
 * queue is busy; this deliberately does not. Someone pressing a button has
 * decided the GPU is theirs, and refusing with "try again later" would be
 * mystifying when the page shows unreviewed items sitting right there.
 *
 * Progress goes through the cache rather than a broadcast, matching
 * RebuildSearchIndexJob. It matters more here than there: a photo costs
 * roughly fifteen seconds of vision inference, so a run of a dozen items is
 * minutes long and a button with no feedback reads as broken.
 */
#[Timeout(3600)]
#[Tries(1)]
class ReviewItemPhotosJob implements ShouldQueue
{
    use Queueable;

    public const STATUS_KEY = 'proposals.review';

    public function __construct(private readonly int $limit = 20) {}

    public function handle(PhotoReviewRunner $runner): void
    {
        $items = Item::awaitingPhotoReview()
            ->orderBy('id')
            ->limit(max(1, $this->limit))
            ->get();

        $total = $items->count();
        $done = 0;
        $proposed = 0;
        $failed = 0;

        $this->putStatus(['state' => 'running', 'done' => 0, 'total' => $total]);

        foreach ($items as $item) {
            try {
                $proposed += $runner->review($item)->count();
            } catch (Throwable $e) {
                // One unreadable photo or a model hiccup must not end the run;
                // the item keeps its unreviewed photos and comes round again.
                $failed++;
                report($e);
            }

            $done++;

            $this->putStatus([
                'state' => 'running',
                'done' => $done,
                'total' => $total,
                'proposed' => $proposed,
                'failed' => $failed,
            ]);
        }

        $this->putStatus([
            'state' => 'done',
            'done' => $done,
            'total' => $total,
            'proposed' => $proposed,
            'failed' => $failed,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->putStatus(['state' => 'failed', 'error' => $exception?->getMessage() ?? 'Photo review failed.']);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function putStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addHour());
    }
}
