<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Parser;

use Osticket\Mail\Exceptions\ParseException;
use Osticket\Mail\Fetch\RawMessage;
use Osticket\Mail\Parser\RawMessageParser;
use PHPUnit\Framework\TestCase;
use Osticket\Mail\Parser\ParsedAttachment;
use ReflectionMethod;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message\IMessagePart;

final class RawMessageParserTest extends TestCase
{
    public function test_attachment_without_a_content_type_uses_the_binary_default(): void
    {
        $part = $this->createMock(IMessagePart::class);
        $part->method('getContentType')->willReturn(null);
        $part->method('getContent')->willReturn('Attachment body');
        $message = $this->createMock(IMessage::class);
        $message->method('getAllAttachmentParts')->willReturn([$part]);

        $extract = new ReflectionMethod(RawMessageParser::class, 'extractAttachments');
        $attachments = $extract->invoke(new RawMessageParser(), $message);

        self::assertIsArray($attachments);
        self::assertCount(1, $attachments);
        self::assertInstanceOf(ParsedAttachment::class, $attachments[0]);
        self::assertSame('application/octet-stream', $attachments[0]->contentType());
        self::assertSame('Attachment body', $attachments[0]->content());
    }

    public function test_it_parses_simple_text_message(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse("Subject: Hello\r\nFrom: from@example.com\r\n\r\nPlain body");

        self::assertSame('Hello', $parsed->subject());
        self::assertSame('from@example.com', $parsed->firstHeader('from'));
        self::assertSame('Plain body', $parsed->textBody());
        self::assertNull($parsed->htmlBody());
        self::assertFalse($parsed->hasAttachments());
    }

    public function test_it_parses_simple_html_message(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse(
            "Subject: Hello\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>Hello</p>"
        );

        self::assertSame('Hello', $parsed->subject());
        self::assertNull($parsed->textBody());
        self::assertSame('<p>Hello</p>', $parsed->htmlBody());
    }

    public function test_it_unfolds_folded_headers(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse("Subject: Hello\r\n folded\r\n\r\nBody");

        self::assertSame('Hello folded', $parsed->subject());
        self::assertSame('Hello folded', $parsed->firstHeader('subject'));
    }

    public function test_it_carries_raw_message_metadata(): void
    {
        $parser = new RawMessageParser();
        $raw = new RawMessage(
            "Subject: Hello\r\n\r\nBody",
            providerId: 'uid-100',
            mailbox: 'INBOX',
            flags: ['Seen']
        );

        $parsed = $parser->parse($raw);

        self::assertSame('uid-100', $parsed->meta()['provider_id']);
        self::assertSame('INBOX', $parsed->meta()['mailbox']);
        self::assertSame(22, $parsed->meta()['size']);
        self::assertSame(['Seen'], $parsed->meta()['flags']);
        self::assertSame('zbateson/mail-mime-parser', $parsed->meta()['parser']);
        self::assertSame(0, $parsed->meta()['attachment_count']);
    }

    public function test_it_parses_multipart_alternative_message(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse($this->crlf(<<<'EOD'
From: Support <support@example.com>
To: User <user@example.com>
Subject: Multipart greeting
Message-ID: <fixture-1@example.com>
MIME-Version: 1.0
Content-Type: multipart/alternative; boundary="alt-boundary"

--alt-boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

Hello plain body.

--alt-boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

<p>Hello <strong>HTML</strong> body.</p>

--alt-boundary--
EOD));

        self::assertSame('Multipart greeting', $parsed->subject());
        self::assertSame('Support <support@example.com>', $parsed->firstHeader('from'));
        self::assertSame('<fixture-1@example.com>', $parsed->firstHeader('message-id'));
        self::assertStringContainsString('Hello plain body.', (string) $parsed->textBody());
        self::assertStringContainsString('<strong>HTML</strong>', (string) $parsed->htmlBody());
        self::assertSame(0, $parsed->meta()['attachment_count']);
    }

    public function test_it_decodes_encoded_subject_and_body(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse($this->crlf(<<<'EOD'
From: =?UTF-8?Q?Support_Team?= <support@example.com>
To: user@example.com
Subject: =?UTF-8?Q?Ol=C3=A1_from_osTicket?=
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

Ol=C3=A1 body.
EOD));

        self::assertSame('Olá from osTicket', $parsed->subject());
        self::assertStringContainsString('Olá body.', (string) $parsed->textBody());
        self::assertNull($parsed->htmlBody());
    }

    public function test_it_extracts_regular_attachment(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse($this->crlf(<<<'EOD'
Subject: Attachment message
From: Support <support@example.com>
To: User <user@example.com>
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="attachment-boundary"

--attachment-boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit

Message body.

--attachment-boundary
Content-Type: text/plain; name="fixture.txt"
Content-Disposition: attachment; filename="fixture.txt"
Content-Transfer-Encoding: base64

QXR0YWNoZWQgZml4dHVyZSBmaWxlLgo=
--attachment-boundary--
EOD));

        self::assertSame('Attachment message', $parsed->subject());
        self::assertTrue($parsed->hasAttachments());
        self::assertSame(1, $parsed->meta()['attachment_count']);

        $attachment = $parsed->attachments()[0];

        self::assertSame('fixture.txt', $attachment->filename());
        self::assertSame('text/plain', $attachment->contentType());
        self::assertSame('attachment', $attachment->disposition());
        self::assertFalse($attachment->isInline());
        self::assertTrue($attachment->isAttachment());
        self::assertSame("Attached fixture file.\n", $attachment->content());
    }

    public function test_it_extracts_inline_cid_attachment_metadata(): void
    {
        $parser = new RawMessageParser();
        $parsed = $parser->parse($this->crlf(<<<'EOD'
Subject: Inline image message
From: Support <support@example.com>
To: User <user@example.com>
MIME-Version: 1.0
Content-Type: multipart/related; boundary="related-boundary"

--related-boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit

<p>Hello <img src="cid:logo@example.com"></p>

--related-boundary
Content-Type: image/png; name="logo.png"
Content-Disposition: inline; filename="logo.png"
Content-ID: <logo@example.com>
Content-Transfer-Encoding: base64

aW1hZ2UtYnl0ZXM=
--related-boundary--
EOD));

        self::assertSame('Inline image message', $parsed->subject());
        self::assertStringContainsString('cid:logo@example.com', (string) $parsed->htmlBody());
        self::assertTrue($parsed->hasAttachments());
        self::assertSame(1, $parsed->meta()['attachment_count']);

        $attachment = $parsed->attachments()[0];

        self::assertSame('logo.png', $attachment->filename());
        self::assertSame('image/png', $attachment->contentType());
        self::assertSame('logo@example.com', $attachment->contentId());
        self::assertSame('inline', $attachment->disposition());
        self::assertTrue($attachment->isInline());
        self::assertFalse($attachment->isAttachment());
        self::assertSame('image-bytes', $attachment->content());
    }

    public function test_it_rejects_empty_content(): void
    {
        $parser = new RawMessageParser();

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('empty');

        $parser->parse('');
    }

    private function crlf(string $message): string
    {
        return \str_replace("\n", "\r\n", $message);
    }
}