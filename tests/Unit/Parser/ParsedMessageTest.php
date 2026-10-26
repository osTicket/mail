<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Parser;

use InvalidArgumentException;
use Osticket\Mail\Parser\ParsedAttachment;
use Osticket\Mail\Parser\ParsedMessage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ParsedMessageTest extends TestCase
{
    public function test_it_normalizes_headers_and_exposes_bodies(): void
    {
        $message = new ParsedMessage(
            headers: [
                'Subject' => 'Hello',
                'Received' => ['one', 'two'],
            ],
            subject: 'Hello',
            textBody: 'Plain body',
            attachments: [new ParsedAttachment('fixture.txt', 'text/plain', 'Attachment body')],
            meta: ['source' => 'fixture']
        );

        self::assertSame(['subject' => ['Hello'], 'received' => ['one', 'two']], $message->headers());
        self::assertSame(['Hello'], $message->header('SUBJECT'));
        self::assertSame('Hello', $message->firstHeader('subject'));
        self::assertSame('Hello', $message->subject());
        self::assertSame('Plain body', $message->textBody());
        self::assertNull($message->htmlBody());
        self::assertTrue($message->hasAttachments());
        self::assertSame('fixture.txt', $message->attachments()[0]->filename());
        self::assertTrue($message->hasTextBody());
        self::assertFalse($message->hasHtmlBody());
        self::assertSame(['source' => 'fixture'], $message->meta());
    }

    public function test_empty_body_values_are_normalized_to_null(): void
    {
        $message = new ParsedMessage(textBody: '', htmlBody: '');

        self::assertNull($message->textBody());
        self::assertNull($message->htmlBody());
    }

    public function test_it_rejects_empty_header_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('header name');

        new ParsedMessage(['' => 'value']);
    }

    public function test_it_rejects_non_string_header_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('header values');

        // Reflection allows deliberately invalid generic array contents through the test boundary.
        (new ReflectionClass(ParsedMessage::class))->newInstanceArgs([['X-Test' => [123]]]);
    }

    public function test_it_rejects_non_attachment_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('attachments');

        // Keep the public PHPDoc contract strict while exercising its runtime validation.
        (new ReflectionClass(ParsedMessage::class))->newInstanceArgs(['attachments' => ['not an attachment']]);
    }
}