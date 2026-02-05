<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Exceptions;

use Osticket\Mail\Exceptions\ParseException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ParseExceptionTest extends TestCase
{
    public function test_it_exposes_message_and_previous(): void
    {
        $previous = new RuntimeException('inner');
        $exception = new ParseException('Parse failed', $previous);

        self::assertSame('Parse failed', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
    }
}