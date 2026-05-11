<?php

namespace App\Exceptions;

use Exception;

class PlatformDisabledException extends Exception
{
    public function __construct(string $platformLabel)
    {
        parent::__construct(
            "{$platformLabel} is currently disabled by admin. Please try again later or use Manual Booking."
        );
    }
}
