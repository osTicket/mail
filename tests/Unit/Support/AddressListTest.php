<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Support;

use InvalidArgumentException;
use Osticket\Mail\Support\{AddressList, EmailAddress};
use PHPUnit\Framework\TestCase;

final class AddressListTest extends TestCase
{
    public function test_it_renders_multiple_addresses(): void
    {
        $list = new AddressList([
            new EmailAddress('a@example.com'),
            new EmailAddress('b@example.com', 'Bee'),
        ]);

        $this->assertSame(
            'a@example.com, "Bee" <b@example.com>',
            $list->toString()
        );
    }

    public function test_it_rejects_empty_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AddressList([]);
    }

    public function test_it_counts_addresses(): void
    {
        $list = AddressList::fromOne(new EmailAddress('a@example.com'));

        $this->assertCount(1, $list);
    }
}
