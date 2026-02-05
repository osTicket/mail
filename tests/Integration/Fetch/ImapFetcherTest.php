<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Integration\Fetch;

use Osticket\Mail\Auth\Credentials\PasswordCredentials;
use Osticket\Mail\Exceptions\FetchException;
use Osticket\Mail\Fetch\{FetchDisposition, FetchWindow, ImapFetcher, RawMessage};
use PHPUnit\Framework\TestCase;

final class ImapFetcherTest extends TestCase
{
    public function test_it_verifies_connection_without_mutating_source_mailbox(): void
    {
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $before = $this->mailboxSnapshot($fetcher, $mailbox);

        $fetcher->verifyConnection();

        self::assertSame($before, $this->mailboxSnapshot($fetcher, $mailbox));
    }

    public function test_it_verifies_mailbox_readiness_without_mutating_messages(): void
    {
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $before = $this->mailboxSnapshot($fetcher, $mailbox);

        $fetcher->verifyMailboxReadiness($mailbox);

        self::assertSame($before, $this->mailboxSnapshot($fetcher, $mailbox));
    }

    public function test_it_provisions_readiness_mailboxes_idempotently(): void
    {
        $fetcher = $this->fetcher();
        $mailbox = $this->requiredEnv('OSTICKET_MAIL_IMAP_READINESS_MAILBOX');
        $target = $this->requiredEnv('OSTICKET_MAIL_IMAP_READINESS_MOVE_MAILBOX');
        $disposition = FetchDisposition::moveTo($target);

        $fetcher->verifyMailboxReadiness($mailbox, $disposition);
        $fetcher->verifyMailboxReadiness($mailbox, $disposition);

        self::assertLessThanOrEqual(1, \count($fetcher->fetch($mailbox, 1)));
        self::assertLessThanOrEqual(1, \count($fetcher->fetch($target, 1)));
    }


    public function test_it_recovers_an_existing_mailbox_after_provider_case_collision(): void
    {
        $mailbox = $this->env('OSTICKET_MAIL_IMAP_CASE_VARIANT_MAILBOX');

        if ($mailbox === null) {
            self::markTestSkipped(
                'Set OSTICKET_MAIL_IMAP_CASE_VARIANT_MAILBOX to an existing mailbox using provider-conflicting casing.'
            );
        }

        $this->fetcher()->verifyMailboxReadiness($mailbox);

        self::addToAssertionCount(1);
    }

    public function test_it_reconnects_lazily_after_explicit_disconnect(): void
    {
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $messages = $fetcher->fetch($mailbox, 1);

        if ($messages === []) {
            self::fail('The configured integration mailbox must contain at least one fixture message.');
        }

        $message = $messages[0];
        $uid = $this->messageUid($message);

        $fetcher->disconnect();

        $resolved = $fetcher->fetchByUid($mailbox, $uid);

        self::assertSame($message->providerId(), $resolved->providerId());
        self::assertSame($message->content(), $resolved->content());
    }

    public function test_it_selects_bounded_windows_oldest_to_newest_and_resolves_exact_uid(): void
    {
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $oldest = $fetcher->fetch($mailbox, 2, FetchWindow::OLDEST);
        $latest = $fetcher->fetch($mailbox, 2);

        if (\count($oldest) < 2 || \count($latest) < 2) {
            self::fail('The configured integration mailbox must contain at least three fixture messages.');
        }

        $oldestUids = \array_map(
            fn (RawMessage $message): int => $this->messageUid($message),
            $oldest
        );
        $latestUids = \array_map(
            fn (RawMessage $message): int => $this->messageUid($message),
            $latest
        );

        $orderedOldest = $oldestUids;
        $orderedLatest = $latestUids;
        \sort($orderedOldest, \SORT_NUMERIC);
        \sort($orderedLatest, \SORT_NUMERIC);

        self::assertSame($orderedOldest, $oldestUids);
        self::assertSame($orderedLatest, $latestUids);
        self::assertLessThan($latestUids[1], $oldestUids[1]);

        $selected = $latest[0];
        $resolved = $fetcher->fetchByUid($mailbox, $this->messageUid($selected));

        self::assertSame($selected->providerId(), $resolved->providerId());
        self::assertSame($mailbox, $resolved->mailbox());
        self::assertSame($selected->content(), $resolved->content());
    }

    public function test_it_marks_an_exact_uid_as_read(): void
    {
        $uid = $this->requiredUid('OSTICKET_MAIL_IMAP_MARK_READ_UID');
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $message = $fetcher->fetchByUid($mailbox, $uid);

        $fetcher->applyDisposition($message, FetchDisposition::markRead());

        $updated = $fetcher->fetchByUid($mailbox, $uid);

        self::assertTrue($updated->hasFlag('Seen'));
    }

    public function test_it_marks_read_and_moves_an_exact_uid(): void
    {
        $uid = $this->requiredUid('OSTICKET_MAIL_IMAP_MOVE_UID');
        $target = $this->requiredEnv('OSTICKET_MAIL_IMAP_MOVE_MAILBOX');
        $fetcher = $this->fetcher();
        $source = $this->sourceMailbox();
        $message = $fetcher->fetchByUid($source, $uid);
        $contentHash = \hash('sha256', $message->content());

        $fetcher->applyDisposition($message, FetchDisposition::moveTo($target));

        try {
            $fetcher->fetchByUid($source, $uid);
            self::fail('Expected the moved source UID to be unavailable.');
        } catch (FetchException) {
            // Expected after a successful move.
        }

        $targetMessages = $fetcher->fetch($target, 100, FetchWindow::LATEST);
        $targetHashes = \array_map(
            static fn (RawMessage $targetMessage): string => \hash('sha256', $targetMessage->content()),
            $targetMessages
        );

        self::assertContains($contentHash, $targetHashes);
    }

    public function test_it_deletes_the_resolved_uid_and_expunges_the_mailbox(): void
    {
        $uid = $this->requiredUid('OSTICKET_MAIL_IMAP_DELETE_UID');
        $fetcher = $this->fetcher();
        $mailbox = $this->sourceMailbox();
        $message = $fetcher->fetchByUid($mailbox, $uid);

        $fetcher->applyDisposition($message, FetchDisposition::delete());

        $this->expectException(FetchException::class);

        $fetcher->fetchByUid($mailbox, $uid);
    }

    /**
     * Capture stable message identity, content, and flag state for readiness assertions.
     *
     * @param ImapFetcher $fetcher Configured live IMAP fetcher.
     * @param string $mailbox Mailbox to snapshot.
     * @return array<int, array{provider_id: string|null, content_hash: string, flags: string[]}>
     */
    private function mailboxSnapshot(ImapFetcher $fetcher, string $mailbox): array
    {
        $snapshot = [];

        foreach ($fetcher->fetch($mailbox, 20) as $message) {
            $flags = $message->flags();
            \sort($flags, \SORT_STRING);

            $snapshot[] = [
                'provider_id' => $message->providerId(),
                'content_hash' => \hash('sha256', $message->content()),
                'flags' => $flags,
            ];
        }

        return $snapshot;
    }

    /**
     * Create a fetcher from the opt-in live IMAP fixture environment.
     *
     * @return ImapFetcher
     */
    private function fetcher(): ImapFetcher
    {
        $this->requireIntegration();

        $port = (int) ($this->env('OSTICKET_MAIL_IMAP_PORT') ?? '993');
        $encryption = $this->env('OSTICKET_MAIL_IMAP_ENCRYPTION') ?? 'ssl';
        $validateCertificate = \filter_var(
            $this->env('OSTICKET_MAIL_IMAP_VALIDATE_CERT') ?? 'true',
            \FILTER_VALIDATE_BOOL
        );
        $timeout = (int) ($this->env('OSTICKET_MAIL_IMAP_TIMEOUT') ?? '30');

        if (\in_array(\strtolower($encryption), ['none', 'null', ''], true)) {
            $encryption = null;
        }

        return new ImapFetcher(
            host: $this->requiredEnv('OSTICKET_MAIL_IMAP_HOST'),
            port: $port,
            encryption: $encryption,
            validateCertificate: $validateCertificate,
            timeoutSeconds: $timeout,
            credentials: new PasswordCredentials(
                $this->requiredEnv('OSTICKET_MAIL_IMAP_USERNAME'),
                $this->requiredEnv('OSTICKET_MAIL_IMAP_PASSWORD')
            )
        );
    }

    /**
     * Return the configured source mailbox.
     *
     * @return string
     */
    private function sourceMailbox(): string
    {
        return $this->env('OSTICKET_MAIL_IMAP_MAILBOX') ?? 'INBOX';
    }

    /**
     * Require explicit opt-in before live IMAP tests execute.
     *
     * @return void
     */
    private function requireIntegration(): void
    {
        if ($this->env('OSTICKET_MAIL_IMAP_INTEGRATION') !== '1') {
            self::markTestSkipped(
                'Set OSTICKET_MAIL_IMAP_INTEGRATION=1 to run live IMAP integration tests.'
            );
        }
    }

    /**
     * Read a required positive UID environment value.
     *
     * @param string $name Environment variable name.
     * @return int
     */
    private function requiredUid(string $name): int
    {
        $this->requireIntegration();
        $value = $this->requiredEnv($name);

        if (\preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            self::fail(\sprintf('%s must contain a positive IMAP UID.', $name));
        }

        return (int) $value;
    }

    /**
     * Read a required environment value or skip the current integration test.
     *
     * @param string $name Environment variable name.
     * @return string
     */
    private function requiredEnv(string $name): string
    {
        $value = $this->env($name);

        if ($value === null) {
            self::markTestSkipped(\sprintf('%s is required for this integration test.', $name));
        }

        \assert($value !== null);

        return $value;
    }

    /**
     * Read a validated UID from a fetched message.
     *
     * @param RawMessage $message Fetched message.
     * @return int
     */
    private function messageUid(RawMessage $message): int
    {
        $providerId = $message->providerId();

        if ($providerId === null || \preg_match('/\A[1-9][0-9]*\z/', $providerId) !== 1) {
            self::fail('Fetched integration message is missing a valid IMAP UID.');
        }

        return (int) $providerId;
    }

    /**
     * Read and normalize an environment value.
     *
     * @param string $name Environment variable name.
     * @return string|null
     */
    private function env(string $name): ?string
    {
        $value = \getenv($name);

        if (!\is_string($value)) {
            return null;
        }

        $value = \trim($value);

        return $value !== '' ? $value : null;
    }
}