<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\EmailAddress;
use PHPUnit\Framework\TestCase;

final class MessageRenderingTest extends TestCase
{
    public function test_to_string_prepares_message_automatically(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Render Test')
            ->setTextBody('Body');

        $rfc = $message->toString();

        self::assertStringContainsString('From: from@example.com', $rfc);
        self::assertStringContainsString('To: to@example.com', $rfc);
        self::assertStringContainsString('Subject: Render Test', $rfc);
        self::assertStringContainsString('Message-ID:', $rfc);
    }

    public function test_rendered_message_uses_crlf_line_endings(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Line Endings')
            ->setTextBody('Body');

        $rfc = $message->toString();

        self::assertStringContainsString("\r\n", $rfc);
        self::assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $rfc);
    }

    public function test_rendering_does_not_reassign_message_id(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Stable ID')
            ->setTextBody('Body');

        $first = $message->toString();
        $second = $message->toString();

        self::assertSame($this->headerLine($first, 'Message-ID'), $this->headerLine($second, 'Message-ID'));
    }

    private function headerLine(string $rfc, string $headerName): string
    {
        if (\preg_match('/^' . \preg_quote($headerName, '/') . ': .*$/m', $rfc, $matches) !== 1) {
            self::fail('Missing ' . $headerName . ' header.');
        }

        return $matches[0];
    }
}