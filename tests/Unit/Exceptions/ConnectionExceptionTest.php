<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Exceptions;

use Osticket\Mail\Exceptions\{AuthenticationException, ConnectionException};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionExceptionTest extends TestCase
{
    public function test_connection_exception_preserves_provider_diagnostics(): void
    {
        $previous = new RuntimeException('provider failure');
        $exception = new ConnectionException(
            'Connection failed.',
            providerCode: '535',
            providerMessage: 'Authentication rejected.',
            previous: $previous
        );

        self::assertSame('535', $exception->providerCode());
        self::assertSame('Authentication rejected.', $exception->providerMessage());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function test_authentication_exception_is_a_connection_exception(): void
    {
        $exception = new AuthenticationException('Authentication failed.');

        self::assertInstanceOf(ConnectionException::class, $exception);
    }
}
