<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use InvalidArgumentException;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use PHPUnit\Framework\TestCase;

final class MessageConvenienceApiTest extends TestCase
{
    public function test_empty_message_can_be_built_with_existing_setters(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('agent@example.com', 'Agent'))
            ->addTo(new EmailAddress('user@example.com', 'User'))
            ->setSubject('Ticket reply')
            ->setTextBody('Hello world')
            ->prepare();

        self::assertInstanceOf(Message::class, $message);
        self::assertSame('agent@example.com', $message->from()?->email());
        self::assertCount(1, $message->to()?->all() ?? []);

        $rfc = $message->toString();

        self::assertStringContainsString('agent@example.com', $rfc);
        self::assertStringContainsString('user@example.com', $rfc);
        self::assertStringContainsString('Subject: Ticket reply', $rfc);
    }

    public function test_constructor_remains_compatible_with_seed_headers(): void
    {
        $message = new Message(
            new EmailAddress('from@example.com'),
            AddressList::fromOne(new EmailAddress('to@example.com')),
            'Hello'
        );

        self::assertSame('from@example.com', $message->from()?->email());
        self::assertSame('Hello', $message->email()->getSubject());
        self::assertCount(1, $message->to()?->all() ?? []);
    }

    public function test_prepare_returns_self_for_chainable_convenience(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        self::assertSame($message, $message->prepare());
    }

    public function test_prepare_rejects_missing_from_address(): void
    {
        $message = (new Message())
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('From address');

        $message->prepare();
    }

    public function test_prepare_rejects_missing_recipients(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one recipient');

        $message->prepare();
    }

    public function test_prepare_rejects_missing_subject(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setTextBody('Body');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subject');

        $message->prepare();
    }

    public function test_with_from_and_set_from_remain_immutable(): void
    {
        $message = new Message();
        $changed = $message->setFrom(new EmailAddress('from@example.com'));

        self::assertNotSame($message, $changed);
        self::assertNull($message->from());
        self::assertSame('from@example.com', $changed->from()?->email());
    }

    public function test_sender_is_distinct_from_from_and_reply_to(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('agent@example.com'))
            ->setSender(new EmailAddress('system@example.com'))
            ->setReplyTo(new EmailAddress('reply@example.com'))
            ->addTo(new EmailAddress('user@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        self::assertSame('agent@example.com', $message->from()?->email());
        self::assertSame('system@example.com', $message->sender()?->email());

        $rfc = $message->toString();

        self::assertStringContainsString('From: agent@example.com', $rfc);
        self::assertStringContainsString('Sender: system@example.com', $rfc);
        self::assertStringContainsString('Reply-To: reply@example.com', $rfc);
    }

    public function test_with_to_replaces_existing_to_recipients(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('first@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $changed = $message->withTo(new AddressList([
            new EmailAddress('second@example.com'),
            new EmailAddress('third@example.com'),
        ]));

        self::assertNotSame($message, $changed);
        self::assertSame(['first@example.com'], \array_map(
            static fn (EmailAddress $address): string => $address->email(),
            $message->to()?->all() ?? []
        ));
        self::assertSame(['second@example.com', 'third@example.com'], \array_map(
            static fn (EmailAddress $address): string => $address->email(),
            $changed->to()?->all() ?? []
        ));

        $rfc = $changed->toString();

        self::assertStringNotContainsString('first@example.com', $rfc);
        self::assertStringContainsString('second@example.com', $rfc);
        self::assertStringContainsString('third@example.com', $rfc);
    }

    public function test_set_to_delegates_to_replacement_semantics(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('first@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        $changed = $message->setTo(AddressList::fromOne(
            new EmailAddress('replacement@example.com')
        ));

        self::assertNotSame($message, $changed);
        self::assertSame(['replacement@example.com'], \array_map(
            static fn (EmailAddress $address): string => $address->email(),
            $changed->to()?->all() ?? []
        ));

        $rfc = $changed->toString();

        self::assertStringNotContainsString('first@example.com', $rfc);
        self::assertStringContainsString('replacement@example.com', $rfc);
    }

    public function test_add_to_still_appends_recipients(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('first@example.com'))
            ->addTo(new EmailAddress('second@example.com'))
            ->setSubject('Hello')
            ->setTextBody('Body');

        self::assertSame(['first@example.com', 'second@example.com'], \array_map(
            static fn (EmailAddress $address): string => $address->email(),
            $message->to()?->all() ?? []
        ));

        $rfc = $message->toString();

        self::assertStringContainsString('first@example.com', $rfc);
        self::assertStringContainsString('second@example.com', $rfc);
    }
}