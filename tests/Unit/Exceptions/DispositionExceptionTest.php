<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Exceptions;

use Osticket\Mail\Exceptions\{DispositionException, MailExceptionInterface};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DispositionExceptionTest extends TestCase
{
    public function test_it_preserves_provider_diagnostics_and_previous_exception(): void
    {
        $previous = new RuntimeException('provider failed');
        $exception = new DispositionException(
            'Disposition failed.',
            providerCode: 'NO',
            providerMessage: 'Mailbox is unavailable.',
            previous: $previous
        );

        self::assertInstanceOf(MailExceptionInterface::class, $exception);
        self::assertSame('NO', $exception->providerCode());
        self::assertSame('Mailbox is unavailable.', $exception->providerMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}
