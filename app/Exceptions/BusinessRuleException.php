<?php

namespace App\Exceptions;

use Exception;

class BusinessRuleException extends Exception
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        protected int $statusCode = 422,
        protected string $errorCode = 'BUSINESS_RULE_VIOLATION',
        protected array $errors = []
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function make(
        string $message,
        int $statusCode = 422,
        string $errorCode = 'BUSINESS_RULE_VIOLATION',
        array $errors = []
    ): self {
        return new self($message, $statusCode, $errorCode, $errors);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
