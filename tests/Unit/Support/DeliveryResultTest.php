<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use Osticket\Mail\Support\DeliveryResult;
use PHPUnit\Framework\TestCase;

final class DeliveryResultTest extends TestCase
{
    public function test_sent_factory_builds_success_result(): void
    {
        $r = DeliveryResult::sent('provider-id-123', ['k' => 'v']);

        self::assertTrue($r->sentSuccessfully());
        self::assertSame('provider-id-123', $r->providerMessageId());
        self::assertNull($r->providerCode());
        self::assertNull($r->providerMessage());
        self::assertSame(['k' => 'v'], $r->meta());
    }

    public function test_failed_factory_builds_failure_result(): void
    {
        $r = DeliveryResult::failed('550', 'Rejected', ['x' => 1]);

        self::assertFalse($r->sentSuccessfully());
        self::assertNull($r->providerMessageId());
        self::assertSame('550', $r->providerCode());
        self::assertSame('Rejected', $r->providerMessage());
        self::assertSame(['x' => 1], $r->meta());
    }
}
