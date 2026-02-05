<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Support\MessageId;
use PHPUnit\Framework\TestCase;

final class MessageIdTest extends TestCase
{
    public function test_it_generates_a_message_id_with_domain(): void
    {
        $id = MessageId::generate('example.com');

        $this->assertStringStartsWith('<', $id->value());
        $this->assertStringEndsWith('>', $id->value());
        $this->assertStringContainsString('@example.com>', $id->value());
    }

    public function test_it_can_be_deterministic_for_tests(): void
    {
        $entropy = static function (int $length): string {
            return str_repeat("\xAB", $length);
        };

        $id = MessageId::generate('Example.COM', $entropy, 1700000000);

        $this->assertSame(
            '<1700000000.' . str_repeat('ab', 16) . '@example.com>',
            $id->value()
        );
    }

    public function test_it_rejects_invalid_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MessageId::generate('not a domain');
    }

    public function test_it_rejects_invalid_shape_when_constructed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MessageId('no-brackets@example.com');
    }

    public function test_it_rejects_whitespace_in_inner_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MessageId('<bad id@example.com>');
    }

    public function test_from_user_appends_domain_when_missing(): void
    {
        $id = MessageId::fromUser('B12345', 'example.com');

        $this->assertSame('<B12345@example.com>', $id->value());
    }

    public function test_from_user_preserves_bracketed_value(): void
    {
        $id = MessageId::fromUser('<B12345@example.com>', 'ignored.com');

        $this->assertSame('<B12345@example.com>', $id->value());
    }

    public function test_from_user_wraps_unbracketed_address(): void
    {
        $id = MessageId::fromUser('B12345@example.com', 'ignored.com');

        $this->assertSame('<B12345@example.com>', $id->value());
    }
}
