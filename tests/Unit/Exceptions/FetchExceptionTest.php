<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Exceptions;

use Osticket\Mail\Exceptions\FetchException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FetchExceptionTest extends TestCase
{
    public function test_it_exposes_provider_diagnostics_and_previous(): void
    {
        $previous = new RuntimeException('inner');
        $exception = new FetchException('Fetch failed', 'AUTH', 'Invalid credentials', $previous);

        self::assertSame('Fetch failed', $exception->getMessage());
        self::assertSame('AUTH', $exception->providerCode());
        self::assertSame('Invalid credentials', $exception->providerMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}