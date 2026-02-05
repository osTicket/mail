<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use PHPUnit\Framework\TestCase;

final class EnvelopeFromMessageTest extends TestCase
{
    public function test_it_creates_default_envelope_from_message_headers(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->setSender(new EmailAddress('sender@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->addCc(new EmailAddress('cc@example.com'))
            ->addBcc(new EmailAddress('bcc@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $envelope = Envelope::fromMessage($message);

        self::assertSame('from@example.com', $envelope->from()->email());
        self::assertSame(
            ['to@example.com', 'cc@example.com', 'bcc@example.com'],
            $envelope->summary()['recipients']
        );
    }

    public function test_it_rejects_message_without_from_address(): void
    {
        $message = (new Message())
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without a From address');

        Envelope::fromMessage($message);
    }

    public function test_it_rejects_message_without_recipients(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $this->expectException(InvalidArgumentException::class);

        Envelope::fromMessage($message);
    }

    public function test_it_uses_final_to_list_after_replacement(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('first@example.com'))
            ->setTo(new AddressList([
                new EmailAddress('second@example.com'),
                new EmailAddress('third@example.com'),
            ]))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $envelope = Envelope::fromMessage($message);

        self::assertSame(
            ['second@example.com', 'third@example.com'],
            $envelope->summary()['recipients']
        );
    }
}