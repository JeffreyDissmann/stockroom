<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Homebox\HomeboxClient;
use App\Services\Homebox\HomeboxImporter;
use App\Services\ItemImageProcessor;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs a Homebox import in the background. Carries only a short-lived token
 * (the payload is encrypted at rest) and reports progress via the cache.
 */
#[Timeout(1800)]
#[Tries(1)]
class ImportFromHomeboxJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public const STATUS_KEY = 'homebox.import';

    public function __construct(
        public readonly string $baseUrl,
        public readonly string $token,
    ) {}

    public function handle(ItemImageProcessor $images): void
    {
        $importer = new HomeboxImporter(new HomeboxClient($this->baseUrl, $this->token), $images);

        $result = $importer->import(onProgress: function (int $done, int $total): void {
            $this->putStatus(['state' => 'running', 'done' => $done, 'total' => $total]);
        });

        $this->putStatus(['state' => 'done'] + $result);
    }

    public function failed(?Throwable $exception): void
    {
        $this->putStatus(['state' => 'failed', 'error' => $exception?->getMessage() ?? 'Import failed.']);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function putStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addHour());
    }
}
