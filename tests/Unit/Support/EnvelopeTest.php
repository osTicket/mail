<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    public function test_it_exposes_from_and_recipients(): void
    {
        $from = new EmailAddress('from@example.com', 'From');
        $recipients = new AddressList([
            new EmailAddress('a@example.com', 'A'),
            new EmailAddress('b@example.com', 'B'),
        ]);

        $env = new Envelope($from, $recipients);

        self::assertSame($from, $env->from());
        self::assertSame($recipients, $env->recipients());

        $emails = [];
        foreach ($env->recipients() as $r) {
            $emails[] = $r->email();
        }

        self::assertSame(['a@example.com', 'b@example.com'], $emails);
    }
}
