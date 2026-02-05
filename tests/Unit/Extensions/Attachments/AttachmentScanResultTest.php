<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Extensions\Attachments;

use Osticket\Mail\Extensions\Attachments\AttachmentScanFinding;
use Osticket\Mail\Extensions\Attachments\AttachmentScanResult;
use PHPUnit\Framework\TestCase;

final class AttachmentScanResultTest extends TestCase
{
    public function test_it_exposes_passed_findings_and_meta(): void
    {
        $findings = [
            new AttachmentScanFinding('a.pdf', 'info', 'ok', 'Clean'),
            new AttachmentScanFinding('b.exe', 'critical', 'malware.detected', 'Bad'),
        ];

        $result = new AttachmentScanResult(false, $findings, ['engine' => 'clamav', 'version' => '1.0']);

        self::assertFalse($result->passed());
        self::assertSame($findings, $result->findings());
        self::assertSame(['engine' => 'clamav', 'version' => '1.0'], $result->meta());
    }

    public function test_it_allows_empty_findings_and_meta(): void
    {
        $result = new AttachmentScanResult(true);

        self::assertTrue($result->passed());
        self::assertSame([], $result->findings());
        self::assertSame([], $result->meta());
    }
}
