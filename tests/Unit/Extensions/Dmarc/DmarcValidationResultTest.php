<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Extensions\Dmarc;

use Osticket\Mail\Extensions\Dmarc\DmarcValidationResult;
use PHPUnit\Framework\TestCase;

final class DmarcValidationResultTest extends TestCase
{
    public function test_it_exposes_passed_policy_disposition_and_meta(): void
    {
        $result = new DmarcValidationResult(
            true,
            'quarantine',
            'quarantine',
            ['aligned' => true, 'reason' => 'dkim_aligned']
        );

        self::assertTrue($result->passed());
        self::assertSame('quarantine', $result->policy());
        self::assertSame('quarantine', $result->disposition());
        self::assertSame(['aligned' => true, 'reason' => 'dkim_aligned'], $result->meta());
    }

    public function test_it_allows_minimal_failed_result(): void
    {
        $result = new DmarcValidationResult(false);

        self::assertFalse($result->passed());
        self::assertNull($result->policy());
        self::assertNull($result->disposition());
        self::assertSame([], $result->meta());
    }
}
