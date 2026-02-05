<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Fetch;

use DateTimeImmutable;
use InvalidArgumentException;
use Osticket\Mail\Fetch\RawMessage;
use PHPUnit\Framework\TestCase;

final class RawMessageTest extends TestCase
{
    public function test_it_stores_raw_message_content_and_metadata(): void
    {
        $receivedAt = new DateTimeImmutable('2026-01-01 12:00:00 UTC');
        $message = new RawMessage(
            "Subject: Hello\r\n\r\nBody",
            providerId: '123',
            mailbox: 'INBOX',
            receivedAt: $receivedAt,
            flags: ['Seen', 'Answered', 'Seen'],
            meta: ['source' => 'imap']
        );

        self::assertSame("Subject: Hello\r\n\r\nBody", $message->content());
        self::assertSame('123', $message->providerId());
        self::assertSame('INBOX', $message->mailbox());
        self::assertSame($receivedAt, $message->receivedAt());
        self::assertSame(22, $message->size());
        self::assertSame(['Seen', 'Answered'], $message->flags());
        self::assertTrue($message->hasFlag('Seen'));
        self::assertFalse($message->hasFlag('Flagged'));
        self::assertSame(['source' => 'imap'], $message->meta());
    }

    public function test_it_accepts_explicit_size(): void
    {
        $message = new RawMessage("Subject: Hello\r\n\r\nBody", size: 4096);

        self::assertSame(4096, $message->size());
    }

    public function test_from_string_builds_message_with_metadata(): void
    {
        $message = RawMessage::fromString("Subject: Hello\r\n\r\nBody", ['fixture' => true]);

        self::assertSame(['fixture' => true], $message->meta());
    }

    public function test_it_rejects_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('content');

        new RawMessage(" \t\n");
    }

    public function test_it_rejects_invalid_flags(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('flags');

        new RawMessage("Subject: Hello\r\n\r\nBody", flags: ['Seen', '']);
    }
}