<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use InvalidArgumentException;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\MessageId;
use PHPUnit\Framework\TestCase;

final class MessageIdSetterTest extends TestCase
{
    public function test_set_message_id_sets_explicit_message_id_header(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setMessageId('<custom@example.com>')
            ->setTextBody('Body')
            ->prepare();

        self::assertSame('<custom@example.com>', $message->messageId());
        self::assertStringContainsString('Message-ID: <custom@example.com>', $message->toString());
    }

    public function test_with_message_id_returns_clone(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $changed = $message->withMessageId(new MessageId('<custom@example.com>'));

        self::assertNotSame($message, $changed);
        self::assertSame('<custom@example.com>', $changed->messageId());
        self::assertNotSame($message->messageId(), $changed->messageId());
    }

    public function test_prepare_preserves_explicit_message_id(): void
    {
        $message = (new Message(
            new EmailAddress('from@example.com'),
            AddressList::fromOne(new EmailAddress('to@example.com')),
            'Hello'
        ))
            ->setMessageId('<explicit@example.com>')
            ->setTextBody('Body');

        $message->prepare();
        $message->prepare();

        self::assertSame('<explicit@example.com>', $message->messageId());
        self::assertSame(1, substr_count($message->toString(), 'Message-ID: <explicit@example.com>'));
    }

    public function test_token_message_id_uses_final_from_domain(): void
    {
        $message = (new Message())
            ->setMessageId('ticket.123.reply.456')
            ->setFrom(new EmailAddress('agent@support.example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body')
            ->prepare();

        self::assertSame('<ticket.123.reply.456@support.example.com>', $message->messageId());
        self::assertStringContainsString('Message-ID: <ticket.123.reply.456@support.example.com>', $message->toString());
    }

    public function test_generated_message_id_uses_final_from_domain(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('first@example.com'))
            ->setFrom(new EmailAddress('final@example.org'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body')
            ->prepare();

        self::assertStringEndsWith('@example.org>', $message->messageId());
    }

    public function test_set_message_id_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message-ID value cannot be empty');

        (new Message())->setMessageId('   ');
    }
}