<?php

declare(strict_types=1);

namespace Osticket\Mail\Exceptions;

use Throwable;

/**
 * Package-level exception for raw message parsing failures.
 */
final class ParseException extends MailException
{
    /**
     * Represents a raw message parsing failure.
     *
     * @param string $message Human-readable failure summary.
     * @param Throwable|null $previous Previous exception when available.
     * @return void
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}