<?php

declare(strict_types=1);

namespace Osticket\Mail\Transport\Exceptions;

use Osticket\Mail\Exceptions\MailException;
use Throwable;

final class TransportException extends MailException
{
    private ?string $providerCode;
    private ?string $providerMessage;

    /**
     * Represents a transport-level failure with optional raw provider diagnostics.
     *
     * @param string $message Human-readable failure summary.
     * @param string|null $providerCode Raw provider code if available (e.g. SMTP code, exit code).
     * @param string|null $providerMessage Raw provider message if available.
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
     * Returns the raw provider code when available.
     *
     * @return string|null
     */
    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    /**
     * Returns the raw provider message when available.
     *
     * @return string|null
     */
    public function providerMessage(): ?string
    {
        return $this->providerMessage;
    }
}
