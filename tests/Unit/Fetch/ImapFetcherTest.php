<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Unit\Fetch;

use InvalidArgumentException;
use Osticket\Mail\Auth\Credentials\{PasswordCredentials, XOAuth2Credentials};
use Osticket\Mail\Fetch\{FetchDisposition, ImapFetcher, RawMessage};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImapFetcherTest extends TestCase
{
    public function test_it_accepts_password_credentials(): void
    {
        $fetcher = new ImapFetcher(
            'imap.example.com',
            credentials: new PasswordCredentials('user@example.com', 'secret')
        );

        self::assertInstanceOf(ImapFetcher::class, $fetcher);
    }

    public function test_it_accepts_xoauth2_credentials(): void
    {
        $fetcher = new ImapFetcher(
            'imap.example.com',
            credentials: new XOAuth2Credentials('user@example.com', 'token')
        );

        self::assertInstanceOf(ImapFetcher::class, $fetcher);
    }

    public function test_disconnect_is_safe_without_an_open_session(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $fetcher->disconnect();
        $fetcher->disconnect();

        self::addToAssertionCount(1);
    }

    public function test_it_rejects_empty_host(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('host');

        new ImapFetcher('');
    }

    public function test_it_rejects_invalid_port(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('port');

        new ImapFetcher('imap.example.com', port: 0);
    }

    public function test_it_rejects_invalid_encryption(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('encryption');

        new ImapFetcher('imap.example.com', encryption: 'invalid');
    }

    public function test_it_rejects_invalid_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('timeout');

        new ImapFetcher('imap.example.com', timeoutSeconds: 0);
    }

    public function test_readiness_rejects_empty_mailbox_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mailbox');

        $fetcher->verifyMailboxReadiness('   ');
    }

    public function test_fetch_rejects_empty_mailbox_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mailbox');

        $fetcher->fetch('   ');
    }

    public function test_fetch_rejects_invalid_limit_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit');

        $fetcher->fetch('INBOX', 0);
    }

    public function test_fetch_by_uid_rejects_empty_mailbox_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mailbox');

        $fetcher->fetchByUid('   ', 1);
    }

    public function test_fetch_by_uid_rejects_invalid_uid_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UID');

        $fetcher->fetchByUid('INBOX', 0);
    }

    public function test_disposition_rejects_missing_mailbox_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');
        $message = new RawMessage(
            "Subject: Test\r\n\r\nBody",
            providerId: '1'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mailbox');

        $fetcher->applyDisposition($message, FetchDisposition::markRead());
    }

    public function test_disposition_rejects_missing_uid_before_connecting(): void
    {
        $fetcher = new ImapFetcher('imap.example.com');
        $message = new RawMessage(
            "Subject: Test\r\n\r\nBody",
            mailbox: 'INBOX'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UID');

        $fetcher->applyDisposition($message, FetchDisposition::markRead());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProviderUidValues(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'leading zero' => ['01'],
            'decimal' => ['1.5'],
            'exponent' => ['1e3'],
            'above unsigned 32-bit maximum' => ['4294967296'],
        ];
    }

    #[DataProvider('invalidProviderUidValues')]
    public function test_disposition_rejects_invalid_provider_uid_before_connecting(string $uid): void
    {
        $fetcher = new ImapFetcher('imap.example.com');
        $message = new RawMessage(
            "Subject: Test\r\n\r\nBody",
            providerId: $uid,
            mailbox: 'INBOX'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UID');

        $fetcher->applyDisposition($message, FetchDisposition::delete());
    }
}