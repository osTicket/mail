<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Message;

use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use PHPUnit\Framework\TestCase;

final class MessagePrivacyTest extends TestCase
{
    public function test_bcc_recipients_are_available_to_envelope_but_not_rendered_headers(): void
    {
        $message = (new Message())
            ->setFrom(new EmailAddress('from@example.com'))
            ->addTo(new EmailAddress('to@example.com'))
            ->addBcc(new EmailAddress('hidden@example.com'))
            ->setSubject('Private')
            ->setTextBody('Body');

        $envelope = Envelope::fromMessage($message);
        $rfc = $message->toString();

        self::assertSame(
            ['to@example.com', 'hidden@example.com'],
            $envelope->summary()['recipients']
        );
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', $rfc);
        self::assertStringNotContainsString('hidden@example.com', $rfc);
    }
}