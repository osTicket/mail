<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use InvalidArgumentException;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\{
    AddressList,
    Attachment,
    EmailAddress,
    InlineImage,
    MessageId
};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\AbstractMultipartPart;

final class MessageTest extends TestCase
{
    private function extractHeaderLine(string $rfc, string $headerName): ?string
    {
        if (preg_match('/^' . preg_quote($headerName, '/') . ':\s.*$/m', $rfc, $m) !== 1) {
            return null;
        }

        return $m[0];
    }

    public function test_it_creates_minimal_message(): void
    {
        $msg = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->setSubject('Hello');

        $email = $msg->email();

        $this->assertSame('Hello', $email->getSubject());
        $this->assertCount(1, $email->getTo());
    }

    public function test_it_rejects_empty_subject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            ''
        );

        $msg->prepare();
    }

    public function test_it_sets_message_id(): void
    {
        $id = new MessageId('<123@example.com>');

        $msg = (new Message())
            ->setFrom(new EmailAddress('a@example.com'))
            ->addTo(new EmailAddress('b@example.com'))
            ->setSubject('Hi')
            ->setMessageId($id);

        $header = $msg->email()->getHeaders()->get('Message-ID');
        self::assertNotNull($header);
        $this->assertSame('<123@example.com>', $header->getBodyAsString());
    }

    public function test_message_is_immutable(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Hi'
        );

        $msg2 = $msg->withText('body');

        $this->assertNotSame($msg, $msg2);
    }

    public function test_it_can_construct_a_full_message_with_all_supported_features(): void
    {
        $messageId = new MessageId('<full@example.com>');

        $msg = (new Message())
            ->setFrom(new EmailAddress('from@example.com', 'Sender'))
            ->withTo(new AddressList([
                new EmailAddress('to1@example.com'),
                new EmailAddress('to2@example.com', 'Two'),
            ]))
            ->withCc(AddressList::fromOne(new EmailAddress('cc@example.com')))
            ->withBcc(AddressList::fromOne(new EmailAddress('bcc@example.com')))
            ->withReplyTo(new EmailAddress('reply@example.com'))
            ->setSubject('Full Message')
            ->setMessageId($messageId)
            ->withText('Plain text body')
            ->withHtml('<p>HTML body</p>')
            ->withAttachment(new Attachment('file.txt', 'text/plain', 'data'))
            ->withInlineImage(new InlineImage('img.png', 'image/png', 'cid123@example.com', 'bin'));

        $email = $msg->email();

        $this->assertSame('Full Message', $email->getSubject());
        $this->assertNotNull($email->getHeaders()->get('Message-ID'));
        $this->assertCount(2, $email->getTo());
        $this->assertCount(1, $email->getCc());
        $this->assertCount(1, $email->getBcc());

        $this->assertCount(1, $msg->attachments());
        $this->assertCount(1, $msg->inlineImages());

        $rfc = $msg->toString();
        $this->assertStringContainsString('Subject: Full Message', $rfc);
    }

    public function test_text_only_message_is_plain_text(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Text'
        );

        $msg = $msg->withText('hello');
        $email = $msg->email();
        $body = $email->getBody();

        $this->assertSame('text', $body->getMediaType());
        $this->assertSame('plain', $body->getMediaSubtype());
    }

    public function test_html_only_message_is_html(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'HTML'
        );

        $msg = $msg->withHtml('<p>hi</p>');
        $email = $msg->email();
        $body = $email->getBody();

        $this->assertSame('text', $body->getMediaType());
        $this->assertSame('html', $body->getMediaSubtype());
    }

    public function test_text_and_html_produces_multipart_alternative(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Both'
        );

        $msg = $msg
            ->withText('plain')
            ->withHtml('<p>html</p>');

        $email = $msg->email();
        $body = $email->getBody();

        $this->assertSame('multipart', $body->getMediaType());
        $this->assertSame('alternative', $body->getMediaSubtype());
    }

    public function test_inline_image_is_wired_with_content_id(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Inline'
        );

        $msg = $msg
            ->withHtml('<img src="cid:logo123@example.com">')
            ->withInlineImage(new InlineImage('logo.png', 'image/png', 'logo123@example.com', 'bin'));

        $email = $msg->email();

        $body = $email->getBody();
        self::assertInstanceOf(AbstractMultipartPart::class, $body);
        $parts = iterator_to_array($body->getParts());

        $found = false;
        foreach ($parts as $part) {
            if ($part instanceof DataPart
                    && trim($part->getContentId(), '<>') === 'logo123@example.com') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Inline image part with Content-ID was not found.');
    }

    public function test_it_renders_rfc_message(): void
    {
        $msg = new Message(
            new EmailAddress('from@example.com'),
            AddressList::fromOne(new EmailAddress('to@example.com')),
            'Hello'
        );

        $msg = $msg
            ->withText('Plain')
            ->withHtml('<p>HTML</p>');

        $rfc = $msg->toString();

        $this->assertIsString($rfc);
        $this->assertStringContainsString("Subject: Hello", $rfc);
        $this->assertStringContainsString("Content-Type: multipart/alternative", $rfc);
    }

    public function test_rendering_is_deterministic(): void
    {
        $msg = new Message(
            new EmailAddress('from@example.com'),
            AddressList::fromOne(new EmailAddress('to@example.com')),
            'Deterministic'
        );

        $msg = $msg->withText('Body');

        $first = $msg->toString();
        $second = $msg->toString();

        $firstId = $this->extractHeaderLine($first, 'Message-ID');
        $secondId = $this->extractHeaderLine($second, 'Message-ID');

        $this->assertNotNull($firstId);
        $this->assertSame($firstId, $secondId);
    }

    public function test_inline_image_appears_in_rfc_output(): void
    {
        $msg = new Message(
            new EmailAddress('from@example.com'),
            AddressList::fromOne(new EmailAddress('to@example.com')),
            'Inline'
        );

        $msg = $msg
            ->withHtml('<img src="cid:logo@example.com">')
            ->withInlineImage(
                new InlineImage('logo.png', 'image/png', 'logo@example.com', 'bin')
            );

        $rfc = $msg->toString();

        $this->assertStringContainsString('Content-ID: <logo@example.com>', $rfc);
        $this->assertStringContainsString('Content-Disposition: inline', $rfc);
    }

    public function test_it_allows_custom_headers(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Hi'
        );

        $msg = $msg
            ->addHeader('In-Reply-To', '<x@y>')
            ->addHeader('References', '<a@b> <c@d>');

        $msg = $msg->setTextBody('x');
        $rfc = $msg->toString();

        $this->assertStringContainsString("In-Reply-To: <x@y>", $rfc);
        $this->assertStringContainsString("References: <a@b> <c@d>", $rfc);
    }

    public function test_alias_methods_delegate_and_remain_immutable(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Hi'
        );

        $msg2 = $msg
            ->setTextBody('plain')
            ->setHtmlBody('<p>html</p>')
            ->addTo(new EmailAddress('c@example.com'))
            ->addCc(new EmailAddress('cc@example.com'))
            ->addBcc(new EmailAddress('bcc@example.com'));

        $this->assertNotSame($msg, $msg2);

        $email = $msg2->email();

        $this->assertCount(2, $email->getTo());
        $this->assertCount(1, $email->getCc());
        $this->assertCount(1, $email->getBcc());
    }

    public function test_message_id_token_is_canonicalized(): void
    {
        $msg = new Message(
            new EmailAddress('a@example.com'),
            AddressList::fromOne(new EmailAddress('b@example.com')),
            'Hi',
            'B12345'
        );

        $header = $msg->email()->getHeaders()->get('Message-ID');
        self::assertNotNull($header);
        $this->assertSame('<B12345@example.com>', $header->getBodyAsString());
    }
}