<?php

declare(strict_types=1);

namespace Osticket\Mail\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for all mail failures in this library.
 */
class MailException extends RuntimeException implements MailExceptionInterface
{
    /**
     * Create a new mail exception with an optional previous throwable.
     *
     * @param string $message Human-readable description of the failure.
     * @param int $code Stable internal code for the failure (defaults to 0).
     * @param Throwable|null $previous Previous exception, if any.
     * @return void
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
