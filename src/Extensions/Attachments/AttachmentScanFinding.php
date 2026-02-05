<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Attachments;

use Osticket\Mail\Support\Attachment;

final class AttachmentScanFinding
{
    private string $filename;
    private string $severity;
    private string $code;
    private string $message;

    /**
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * Creates a scan finding for an attachment.
     *
     * @param string $filename Attachment filename.
     * @param string $severity Finding severity (e.g. "info", "warning", "error", "critical").
     * @param string $code Stable finding code.
     * @param string $message Human-readable finding message.
     * @param array<string, mixed> $meta Optional diagnostics.
     * @return void
     */
    public function __construct(
        string $filename,
        string $severity,
        string $code,
        string $message,
        array $meta = []
    ) {
        $this->filename = $filename;
        $this->severity = $severity;
        $this->code = $code;
        $this->message = $message;
        $this->meta = $meta;
    }

    /**
     * Creates a finding for a specific attachment.
     *
     * @param Attachment $attachment
     * @param string $severity
     * @param string $code
     * @param string $message
     * @param array<string, mixed> $meta
     * @return self
     */
    public static function forAttachment(
        Attachment $attachment,
        string $severity,
        string $code,
        string $message,
        array $meta = []
    ): self {
        return new self($attachment->filename(), $severity, $code, $message, $meta);
    }

    /**
     * Returns the filename of the scanned attachment.
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Returns the severity level of the finding.
     *
     * @return string
     */
    public function severity(): string
    {
        return $this->severity;
    }

    /**
     * Returns the stable code identifying the finding.
     *
     * @return string
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Returns the human-readable finding message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
