<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Transport;

use Osticket\Mail\Transport\Exceptions\TransportException;
use PHPUnit\Framework\TestCase;

final class TransportExceptionTest extends TestCase
{
    public function test_it_exposes_provider_diagnostics_and_previous(): void
    {
        $prev = new \RuntimeException('inner');

        $e = new TransportException('Failed', '550', 'Nope', $prev);

        self::assertSame('Failed', $e->getMessage());
        self::assertSame('550', $e->providerCode());
        self::assertSame('Nope', $e->providerMessage());
        self::assertSame($prev, $e->getPrevious());
    }
}
