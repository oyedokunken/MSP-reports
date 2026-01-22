<?php

namespace App\Exceptions;

use Exception;

class ApiResponseException extends Exception
{
    public int $status;
    public string $body;

    public function __construct(string $message, int $status, string $body = '')
    {
        parent::__construct($message);
        $this->status = $status;
        $this->body   = $body;
    }
}