<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Support\EmailAddress;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_it_formats_plain_email(): void
    {
        $addr = new EmailAddress('user@example.com');

        $this->assertSame('user@example.com', $addr->toString());
    }

    public function test_it_formats_named_email(): void
    {
        $addr = new EmailAddress('user@example.com', 'John Doe');

        $this->assertSame('"John Doe" <user@example.com>', $addr->toString());
    }

    public function test_it_escapes_quotes_in_name(): void
    {
        $addr = new EmailAddress('user@example.com', 'John "JD" Doe');

        $this->assertSame('"John \"JD\" Doe" <user@example.com>', $addr->toString());
    }

    public function test_it_rejects_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress('not-an-email');
    }
}
