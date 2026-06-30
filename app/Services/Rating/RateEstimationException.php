<?php

namespace App\Services\Rating;

use RuntimeException;

/**
 * A known, user-facing reason an estimate can't be produced (unknown destination,
 * non-US origin, missing weight, …). The controller turns these into a 422 with
 * the message shown inline — distinct from an unexpected server error.
 */
class RateEstimationException extends RuntimeException {}
