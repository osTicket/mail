<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Auth;

use Osticket\Mail\Auth\XOAuth2Formatter;
use Osticket\Mail\Auth\Credentials\XOAuth2Credentials;
use PHPUnit\Framework\TestCase;

final class XOAuth2FormatterTest extends TestCase
{
    public function test_it_formats_xoauth2_payload(): void
    {
        $creds = new XOAuth2Credentials(
            'user@example.com',
            'ACCESS_TOKEN'
        );

        $formatter = new XOAuth2Formatter();
        $encoded = $formatter->format($creds);

        $decoded = base64_decode($encoded, true);

        self::assertSame(
            "user=user@example.com\x01auth=Bearer ACCESS_TOKEN\x01\x01",
            $decoded
        );
    }
}
