<?php

declare(strict_types=1);

namespace App\Services\Proposals\Exceptions;

use RuntimeException;

/**
 * The review could not be carried out — the model was unreachable, timed out, or
 * refused every photo.
 *
 * Distinct from a review that ran and found nothing worth suggesting. The two
 * look the same to a caller unless one of them is loud, and a whole run of
 * timeouts once reported as "nothing to add" for every item, which reads as a
 * clean bill of health.
 */
class PhotoReviewFailed extends RuntimeException {}
