<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Transport;

use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use Osticket\Mail\Transport\Exceptions\TransportException;
use Osticket\Mail\Transport\SmtpTransport;
use Osticket\Mail\Transport\SendmailTransport;
use PHPUnit\Framework\TestCase;

final class TransportGuardsTest extends TestCase
{
    public function test_smtp_throws_on_empty_rfc822(): void
    {
        $t = new SmtpTransport('127.0.0.1', 25);

        $env = new Envelope(
            new EmailAddress('from@example.com'),
            new AddressList([new EmailAddress('to@example.com')])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Empty RFC822 payload.');
        $t->send($env, '   ');
    }

    public function test_sendmail_throws_on_empty_rfc822(): void
    {
        // Command won't be reached because guard triggers first.
        $t = new SendmailTransport('/usr/sbin/sendmail -bs');

        $env = new Envelope(
            new EmailAddress('from@example.com'),
            new AddressList([new EmailAddress('to@example.com')])
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Empty RFC822 payload.');
        $t->send($env, '');
    }
}
