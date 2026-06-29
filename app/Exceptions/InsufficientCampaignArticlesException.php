<?php

namespace App\Exceptions;

use Exception;

class InsufficientCampaignArticlesException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $requestedCount = 0,
        public readonly int $reservedCount = 0,
    ) {
        parent::__construct($message);
    }
}
