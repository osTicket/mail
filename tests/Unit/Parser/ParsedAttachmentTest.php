<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Parser;

use InvalidArgumentException;
use Osticket\Mail\Parser\ParsedAttachment;
use PHPUnit\Framework\TestCase;

final class ParsedAttachmentTest extends TestCase
{
    public function test_it_exposes_regular_attachment_values(): void
    {
        $attachment = new ParsedAttachment(
            filename: 'fixture.txt',
            contentType: 'Text/Plain',
            content: 'Attachment body',
            disposition: 'attachment',
            meta: ['index' => 0]
        );

        self::assertSame('fixture.txt', $attachment->filename());
        self::assertSame('text/plain', $attachment->contentType());
        self::assertSame('Attachment body', $attachment->content());
        self::assertNull($attachment->contentId());
        self::assertSame('attachment', $attachment->disposition());
        self::assertFalse($attachment->isInline());
        self::assertTrue($attachment->isAttachment());
        self::assertSame(15, $attachment->size());
        self::assertSame(['index' => 0], $attachment->meta());
    }

    public function test_it_exposes_inline_cid_values(): void
    {
        $attachment = new ParsedAttachment(
            filename: 'logo.png',
            contentType: 'Image/PNG',
            content: 'image-bytes',
            contentId: '<logo@example.com>',
            disposition: 'inline'
        );

        self::assertSame('logo.png', $attachment->filename());
        self::assertSame('image/png', $attachment->contentType());
        self::assertSame('logo@example.com', $attachment->contentId());
        self::assertSame('inline', $attachment->disposition());
        self::assertTrue($attachment->isInline());
        self::assertFalse($attachment->isAttachment());
    }

    public function test_content_id_marks_part_inline_even_without_disposition(): void
    {
        $attachment = new ParsedAttachment(
            filename: 'logo.png',
            contentType: 'image/png',
            content: 'image-bytes',
            contentId: 'logo@example.com'
        );

        self::assertTrue($attachment->isInline());
        self::assertFalse($attachment->isAttachment());
    }

    public function test_it_rejects_empty_content_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('content type');

        new ParsedAttachment(
            filename: 'fixture.txt',
            contentType: '',
            content: 'Attachment body'
        );
    }

    public function test_it_rejects_empty_optional_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename');

        new ParsedAttachment(
            filename: '   ',
            contentType: 'text/plain',
            content: 'Attachment body'
        );
    }
}