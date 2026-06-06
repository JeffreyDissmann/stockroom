<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Shared completed_at handling for the maintenance write tools: today when
 * omitted, else a parsed YYYY-MM-DD that may be backdated but never lie in
 * the future — completion is a record of something that happened. Returns
 * the model-facing error string on invalid input (the tool-call convention).
 */
trait ParsesCompletionDates
{
    private function parseCompletedAt(mixed $raw): CarbonImmutable|string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return today()->toImmutable();
        }

        try {
            $date = CarbonImmutable::parse(trim((string) $raw))->startOfDay();
        } catch (Throwable) {
            return 'The completed_at date could not be parsed — use YYYY-MM-DD.';
        }

        if ($date->gt(today())) {
            return 'The completion date cannot be in the future.';
        }

        return $date;
    }
}
