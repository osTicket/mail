<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

final class DeliveryResult
{
    private bool $sent;
    private ?string $providerMessageId;
    private ?string $providerCode;
    private ?string $providerMessage;

    /** @var array<string, mixed> */
    private array $meta;

    /**
     * Represents a single transport delivery attempt outcome.
     *
     * @param bool $sent True when the transport accepted the message for delivery.
     * @param string|null $providerMessageId Provider/transport message identifier if available.
     * @param string|null $providerCode Raw provider error/status code if available (e.g. SMTP code, exit code).
     * @param string|null $providerMessage Raw provider error/status message if available.
     * @param array<string, mixed> $meta Small diagnostic metadata (no logging or policy meaning).
     * @return void
     */
    public function __construct(
        bool $sent,
        ?string $providerMessageId = null,
        ?string $providerCode = null,
        ?string $providerMessage = null,
        array $meta = []
    ) {
        $this->sent = $sent;
        $this->providerMessageId = $providerMessageId;
        $this->providerCode = $providerCode;
        $this->providerMessage = $providerMessage;
        $this->meta = $meta;
    }

    /**
     * Builds a successful delivery result.
     *
     * @param string|null $providerMessageId Provider/transport message identifier if available.
     * @param array<string, mixed> $meta Small diagnostic metadata.
     * @return self
     */
    public static function sent(?string $providerMessageId = null, array $meta = []): self
    {
        return new self(true, $providerMessageId, null, null, $meta);
    }

    /**
     * Builds a failed delivery result using raw provider failure details.
     *
     * @param string|null $providerCode Raw provider code (e.g. SMTP code or exit code).
     * @param string|null $providerMessage Raw provider message.
     * @param array<string, mixed> $meta Small diagnostic metadata.
     * @return self
     */
    public static function failed(?string $providerCode = null, ?string $providerMessage = null, array $meta = []): self
    {
        return new self(false, null, $providerCode, $providerMessage, $meta);
    }

    /**
     * Indicates whether the transport accepted the message for delivery.
     *
     * @return bool
     */
    public function sentSuccessfully(): bool
    {
        return $this->sent;
    }

    /**
     * Returns the provider/transport message identifier when available.
     *
     * @return string|null
     */
    public function providerMessageId(): ?string
    {
        return $this->providerMessageId;
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

    /**
     * Returns transport diagnostic metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
