<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Attachments;

use Osticket\Mail\Support\Attachment;

/**
 * Scans message attachments for malware, policy violations, or unsafe content.
 */
interface AttachmentScannerInterface
{
    /**
     * Scans attachments and returns a structured result.
     *
     * @param array<int, Attachment> $attachments Attachments to scan.
     * @param array<string, mixed> $context Optional scanning context.
     * @return AttachmentScanResult
     */
    public function scan(array $attachments, array $context = []): AttachmentScanResult;
}
