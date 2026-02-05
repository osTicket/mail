<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\EmailAddress;
use PHPUnit\Framework\TestCase;

final class MessageHeaderTest extends TestCase
{
    public function test_add_header_preserves_multiple_values(): void
    {
        $message = $this->message()
            ->addHeader('X-osTicket-Trace', 'first')
            ->addHeader('X-osTicket-Trace', 'second');

        $rfc = $message->toString();

        self::assertStringContainsString('X-osTicket-Trace: first', $rfc);
        self::assertStringContainsString('X-osTicket-Trace: second', $rfc);
    }

    public function test_set_header_replaces_existing_values(): void
    {
        $message = $this->message()
            ->addHeader('X-osTicket-Trace', 'old')
            ->setHeader('X-osTicket-Trace', 'new');

        $rfc = $message->toString();

        self::assertStringNotContainsString('X-osTicket-Trace: old', $rfc);
        self::assertStringContainsString('X-osTicket-Trace: new', $rfc);
    }

    public function test_remove_header_removes_existing_values(): void
    {
        $message = $this->message()
            ->addHeader('X-osTicket-Trace', 'first')
            ->addHeader('X-osTicket-Trace', 'second')
            ->removeHeader('X-osTicket-Trace');

        self::assertStringNotContainsString('X-osTicket-Trace:', $message->toString());
    }

    public function test_header_mutators_are_immutable(): void
    {
        $message = $this->message();
        $changed = $message->addHeader('X-osTicket-Trace', 'value');

        self::assertNotSame($message, $changed);
        self::assertStringNotContainsString('X-osTicket-Trace:', $message->toString());
        self::assertStringContainsString('X-osTicket-Trace: value', $changed->toString());
    }

    private function message(): Message
    {
        return (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Header Test')
            ->setTextBody('Body');
    }
}