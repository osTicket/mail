<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\Attachment;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\InlineImage;
use PHPUnit\Framework\TestCase;

final class MessagePreparationTest extends TestCase
{
    public function test_prepare_and_render_are_idempotent(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Files')
            ->setHtmlBody('<img src="cid:logo@example.com">')
            ->addAttachment(new Attachment('file.txt', 'text/plain', 'data'))
            ->addInlineImage(new InlineImage('logo.png', 'image/png', 'logo@example.com', 'bin'));

        $message->prepare();
        $message->prepare();
        $first = $message->toString();
        $second = $message->toString();

        self::assertSame($first, $second);
        self::assertCount(2, $message->email()->getAttachments());
        self::assertSame(1, substr_count($second, 'Content-ID: <logo@example.com>'));
    }
}