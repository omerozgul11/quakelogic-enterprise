<?php

namespace App\Services\BidSources\SamGov;

use RuntimeException;

/**
 * Thrown when SAM.gov rejects a request because the daily quota is exhausted
 * (HTTP 429) or the service is temporarily unavailable (5xx). Callers should
 * treat this as transient — retry later — and must NOT record it as a
 * definitive "no result", so a throttled lookup never poisons a cache.
 */
class SamThrottledException extends RuntimeException
{
}
