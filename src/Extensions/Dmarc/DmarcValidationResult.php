<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Dmarc;

final class DmarcValidationResult
{
    private bool $passed;
    private ?string $policy;
    private ?string $disposition;

    /**
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * Creates a DMARC validation result.
     *
     * @param bool $passed Whether the message passes validation.
     * @param null|string $policy Optional policy (e.g. "none", "quarantine", "reject").
     * @param null|string $disposition Optional disposition recommendation.
     * @param array<string, mixed> $meta Optional diagnostics.
     * @return void
     */
    public function __construct(bool $passed, ?string $policy = null, ?string $disposition = null, array $meta = [])
    {
        $this->passed = $passed;
        $this->policy = $policy;
        $this->disposition = $disposition;
        $this->meta = $meta;
    }

    /**
     * Indicates whether DMARC validation passed.
     *
     * @return bool
     */
    public function passed(): bool
    {
        return $this->passed;
    }

    /**
     * Returns the DMARC policy applied during validation, if known.
     *
     * @return null|string
     */
    public function policy(): ?string
    {
        return $this->policy;
    }

    /**
     * Returns the recommended DMARC disposition, if any.
     *
     * @return null|string
     */
    public function disposition(): ?string
    {
        return $this->disposition;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
