<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Extensions\Dkim;

use Osticket\Mail\Extensions\Dkim\DkimSignResult;
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use PHPUnit\Framework\TestCase;

final class DkimSignResultTest extends TestCase
{
    private function message(): Message
    {
        $from = new EmailAddress('from@example.com');
        $to = AddressList::fromOne(new EmailAddress('to@example.com'));

        return (new Message($from, $to, 'Subject'))->withText('Body');
    }

    public function test_it_exposes_signed_message_and_meta(): void
    {
        $message = $this->message();

        $result = new DkimSignResult(true, $message, ['selector' => 's1', 'domain' => 'example.com']);

        self::assertTrue($result->signed());
        self::assertSame($message, $result->message());
        self::assertSame(['selector' => 's1', 'domain' => 'example.com'], $result->meta());
    }

    public function test_it_allows_unsigned_result_without_meta(): void
    {
        $message = $this->message();

        $result = new DkimSignResult(false, $message);

        self::assertFalse($result->signed());
        self::assertSame($message, $result->message());
        self::assertSame([], $result->meta());
    }
}
