<?php

declare(strict_types=1);

namespace Osticket\Mail\Exceptions;

use Throwable;

/**
 * Package-level exception for post-fetch mailbox disposition failures.
 */
final class DispositionException extends MailException
{
    private ?string $providerCode;
    private ?string $providerMessage;

    /**
     * Represents a mailbox disposition failure with optional provider diagnostics.
     *
     * @param string $message Human-readable failure summary.
     * @param string|null $providerCode Raw provider or backend code if available.
     * @param string|null $providerMessage Raw provider or backend message if available.
     * @param Throwable|null $previous Previous exception when available.
     * @return void
     */
    public function __construct(
        string $message,
        ?string $providerCode = null,
        ?string $providerMessage = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->providerCode = $providerCode;
        $this->providerMessage = $providerMessage;
    }

    /**
     * Returns the raw provider or backend code when available.
     *
     * @return string|null
     */
    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    /**
     * Returns the raw provider or backend message when available.
     *
     * @return string|null
     */
    public function providerMessage(): ?string
    {
        return $this->providerMessage;
    }
}
