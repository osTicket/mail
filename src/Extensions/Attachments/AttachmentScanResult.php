<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Attachments;

final class AttachmentScanResult
{
    private bool $passed;

    /**
     * @var array<int, AttachmentScanFinding>
     */
    private array $findings;

    /**
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * Creates an attachment scan result.
     *
     * @param bool $passed Whether the scan passed overall.
     * @param array<int, AttachmentScanFinding> $findings Findings produced by the scan.
     * @param array<string, mixed> $meta Optional diagnostics.
     * @return void
     */
    public function __construct(bool $passed, array $findings = [], array $meta = [])
    {
        $this->passed = $passed;
        $this->findings = $findings;
        $this->meta = $meta;
    }

    /**
     * Indicates whether the attachment scan passed overall.
     *
     * @return bool
     */
    public function passed(): bool
    {
        return $this->passed;
    }

    /**
     * @return array<int, AttachmentScanFinding>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
