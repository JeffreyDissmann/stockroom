<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use RuntimeException;

/**
 * Domain-level error raised by PaperlessClient for any API failure we
 * surface to the caller: unauthorised tokens, unreachable hosts, doc
 * not found, missing required custom field, malformed responses.
 *
 * Distinct from generic HTTP exceptions so the queue job can catch this
 * specifically and turn it into a failed-intake row.
 */
class PaperlessException extends RuntimeException {}
