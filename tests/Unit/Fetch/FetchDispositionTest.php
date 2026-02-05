<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Fetch;

use InvalidArgumentException;
use Osticket\Mail\Fetch\{FetchDisposition, FetchWindow};
use PHPUnit\Framework\TestCase;

final class FetchDispositionTest extends TestCase
{
    public function test_mark_read_is_non_destructive_and_recommends_latest_window(): void
    {
        $disposition = FetchDisposition::markRead();

        self::assertSame(FetchDisposition::MARK_READ, $disposition->action());
        self::assertNull($disposition->mailbox());
        self::assertTrue($disposition->shouldMarkRead());
        self::assertFalse($disposition->shouldMove());
        self::assertFalse($disposition->shouldDelete());
        self::assertFalse($disposition->isDestructive());
        self::assertSame(FetchWindow::LATEST, $disposition->fetchWindow());
    }

    public function test_move_is_destructive_and_recommends_oldest_window(): void
    {
        $disposition = FetchDisposition::moveTo(' INBOX.Processed ');

        self::assertSame(FetchDisposition::MOVE, $disposition->action());
        self::assertSame('INBOX.Processed', $disposition->mailbox());
        self::assertFalse($disposition->shouldMarkRead());
        self::assertTrue($disposition->shouldMove());
        self::assertFalse($disposition->shouldDelete());
        self::assertTrue($disposition->isDestructive());
        self::assertSame(FetchWindow::OLDEST, $disposition->fetchWindow());
    }

    public function test_delete_is_destructive_and_recommends_oldest_window(): void
    {
        $disposition = FetchDisposition::delete();

        self::assertSame(FetchDisposition::DELETE, $disposition->action());
        self::assertNull($disposition->mailbox());
        self::assertFalse($disposition->shouldMarkRead());
        self::assertFalse($disposition->shouldMove());
        self::assertTrue($disposition->shouldDelete());
        self::assertTrue($disposition->isDestructive());
        self::assertSame(FetchWindow::OLDEST, $disposition->fetchWindow());
    }

    public function test_move_rejects_empty_mailbox(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mailbox');

        FetchDisposition::moveTo('   ');
    }
}
