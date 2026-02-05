<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Extensions\Attachments;

use Osticket\Mail\Extensions\Attachments\AttachmentScanFinding;
use Osticket\Mail\Support\Attachment;
use PHPUnit\Framework\TestCase;

final class AttachmentScanFindingTest extends TestCase
{
    public function test_it_exposes_fields_and_meta(): void
    {
        $finding = new AttachmentScanFinding(
            'invoice.pdf',
            'critical',
            'malware.detected',
            'Malware signature detected.',
            ['engine' => 'clamav']
        );

        self::assertSame('invoice.pdf', $finding->filename());
        self::assertSame('critical', $finding->severity());
        self::assertSame('malware.detected', $finding->code());
        self::assertSame('Malware signature detected.', $finding->message());
        self::assertSame(['engine' => 'clamav'], $finding->meta());
    }

    public function test_for_attachment_uses_attachment_filename(): void
    {
        $attachment = new Attachment('photo.png', 'image/png', 'binary');

        $finding = AttachmentScanFinding::forAttachment(
            $attachment,
            'warning',
            'policy.blocked',
            'Attachment blocked by policy.',
            ['rule' => 'no_images']
        );

        self::assertSame('photo.png', $finding->filename());
        self::assertSame('warning', $finding->severity());
        self::assertSame('policy.blocked', $finding->code());
        self::assertSame('Attachment blocked by policy.', $finding->message());
        self::assertSame(['rule' => 'no_images'], $finding->meta());
    }
}
