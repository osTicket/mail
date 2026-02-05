<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Fetch;

use Osticket\Mail\Fetch\FetchWindow;
use PHPUnit\Framework\TestCase;

final class FetchWindowTest extends TestCase
{
    public function test_it_exposes_stable_mailbox_window_values(): void
    {
        self::assertSame('oldest', FetchWindow::OLDEST->value);
        self::assertSame('latest', FetchWindow::LATEST->value);
    }
}
