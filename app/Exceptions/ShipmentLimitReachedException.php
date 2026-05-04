<?php

namespace App\Exceptions;

use Exception;

class ShipmentLimitReachedException extends Exception
{
    public function __construct(int $limit)
    {
        parent::__construct(
            "Your shipment limit of {$limit} has been reached. Please apply for a limit extension."
        );
    }
}
