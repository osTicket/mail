<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit;

use Osticket\Mail\Exceptions\{MailException, MailExceptionInterface};
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class SmokeTest extends TestCase
{
    /**
     * Assert the library autoloading and base exception contracts are wired correctly.
     *
     * @return void
     */
    public function test_it_autoloads_and_exposes_base_exception_contracts(): void
    {
        $e = new MailException('boom');

        $this->assertInstanceOf(MailExceptionInterface::class, $e);
        $this->assertSame('boom', $e->getMessage());
    }
}
