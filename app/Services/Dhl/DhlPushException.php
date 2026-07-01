<?php

namespace App\Services\Dhl;

use RuntimeException;

/** Raised when a call to DHL's tracking push API (subscription mgmt) fails. */
class DhlPushException extends RuntimeException
{
}
