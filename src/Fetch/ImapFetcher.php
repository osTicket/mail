<?php

declare(strict_types=1);

namespace Osticket\Mail\Fetch;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Osticket\Mail\Auth\Credentials\{AuthCredentials, PasswordCredentials, XOAuth2Credentials};
use Osticket\Mail\Exceptions\{
    AuthenticationException,
    ConnectionException,
    DispositionException,
    FetchException
};
use Throwable;
use Webklex\PHPIMAP\Client as WebklexClient;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException as WebklexAuthFailedException;
use Webklex\PHPIMAP\Folder as WebklexFolder;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message as WebklexMessage;

/**
 * Fetches raw RFC/MIME messages and applies explicit IMAP mailbox dispositions.
 */
final class ImapFetcher
{
    private const MAX_UID = 4294967295;

    private string $host;
    private int $port;
    private ?string $encryption;
    private bool $validateCertificate;
    private int $timeoutSeconds;
    private ?AuthCredentials $credentials;
    private ClientManager $clientManager;
    private ?WebklexClient $client = null;

    /**
     * Creates a Webklex-backed IMAP fetcher for a concrete mailbox backend.
     *
     * Account selection, token refresh, retry policy, and persistence are owned by the consumer.
     *
     * @param string $host IMAP server host.
     * @param int $port IMAP server port.
     * @param string|null $encryption IMAP encryption mode such as "ssl", "tls", or null.
     * @param bool $validateCertificate Whether TLS certificates should be validated.
     * @param int $timeoutSeconds Connection timeout in seconds.
     * @param AuthCredentials|null $credentials Optional authentication credentials.
     * @param array<string, mixed> $options Additional webklex/php-imap package configuration options.
     * @return void
     *
     * @throws InvalidArgumentException When connection values are invalid.
     */
    public function __construct(
        string $host,
        int $port = 993,
        ?string $encryption = 'ssl',
        bool $validateCertificate = true,
        int $timeoutSeconds = 30,
        ?AuthCredentials $credentials = null,
        array $options = []
    ) {
        $this->host = $this->normalizeHost($host);
        $this->port = $this->normalizePort($port);
        $this->encryption = $this->normalizeEncryption($encryption);
        $this->validateCertificate = $validateCertificate;
        $this->timeoutSeconds = $this->normalizeTimeout($timeoutSeconds);
        $this->credentials = $credentials;
        $this->clientManager = new ClientManager($options);
    }

    /**
     * Verifies that the configured IMAP session can be established without touching a mailbox.
     *
     * @return void
     *
     * @throws AuthenticationException When IMAP authentication is reliably rejected.
     * @throws ConnectionException When the IMAP session cannot be established.
     */
    public function verifyConnection(): void
    {
        try {
            $this->withIsolatedClient(static fn (WebklexClient $client): null => null);
        } catch (ConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->connectionException(
                'IMAP connection verification failed.',
                $e
            );
        }
    }

    /**
     * Verifies and provisions mailbox resources required for IMAP fetch readiness.
     *
     * @param string $mailbox Source fetch mailbox.
     * @param FetchDisposition|null $disposition Configured post-fetch disposition when mutation is required.
     * @return void
     *
     * @throws AuthenticationException When IMAP authentication is reliably rejected.
     * @throws ConnectionException When mailbox readiness cannot be established.
     * @throws InvalidArgumentException When the source mailbox is invalid.
     */
    public function verifyMailboxReadiness(
        string $mailbox,
        ?FetchDisposition $disposition = null
    ): void {
        $mailbox = $this->normalizeMailbox($mailbox);
        $target = $disposition?->mailbox();

        if ($target !== null) {
            $target = $this->normalizeMailbox($target);
        }

        try {
            $this->withIsolatedClient(function (WebklexClient $client) use (
                $mailbox,
                $target
            ): void {
                $this->ensureMailboxReady($client, $mailbox);

                if ($target !== null && $target !== $mailbox) {
                    $this->ensureMailboxReady($client, $target);
                }
            });
        } catch (ConnectionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->connectionException(
                \sprintf('IMAP mailbox readiness failed for "%s".', $mailbox),
                $e
            );
        }
    }

    /**
     * Fetches a bounded mailbox window without applying post-fetch disposition.
     *
     * The selected messages are returned oldest-to-newest. Retrieval attempts to preserve read
     * state through the backend's peek behavior, but read-state behavior remains provider-specific.
     *
     * @param string $mailbox Mailbox or folder name.
     * @param int $limit Maximum number of raw messages to return.
     * @param FetchWindow $window Bounded mailbox window to select.
     * @return RawMessage[]
     *
     * @throws FetchException When the IMAP backend cannot be queried.
     * @throws InvalidArgumentException When mailbox or limit is invalid.
     */
    public function fetch(
        string $mailbox = 'INBOX',
        int $limit = 50,
        FetchWindow $window = FetchWindow::LATEST
    ): array {
        $mailbox = $this->normalizeMailbox($mailbox);

        if ($limit < 1) {
            throw new InvalidArgumentException('IMAP fetch limit must be greater than zero.');
        }

        try {
            return $this->withSessionClient(function (WebklexClient $client) use (
                $mailbox,
                $limit,
                $window
            ): array {
                $folder = $client->getFolder($mailbox);

                if ($folder === null) {
                    throw new FetchException(
                        \sprintf('IMAP mailbox "%s" could not be found.', $mailbox)
                    );
                }

                $query = $folder
                    ->messages()
                    ->whereAll()
                    ->leaveUnread()
                    ->setSequence(IMAP::ST_UID)
                    ->setFetchBody(true)
                    ->limit($limit);

                if ($window === FetchWindow::OLDEST) {
                    $query->setFetchOrderAsc();
                } else {
                    $query->setFetchOrderDesc();
                }

                /** @var iterable<WebklexMessage> $fetched */
                $fetched = $query->get();
                $messages = [];

                foreach ($this->oldestToNewest($fetched) as $selected) {
                    $messages[] = $this->toRawMessage(
                        $selected['message'],
                        $mailbox,
                        $selected['uid']
                    );
                }

                return $messages;
            });
        } catch (FetchException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FetchException(
                \sprintf('Unable to fetch IMAP messages from mailbox "%s".', $mailbox),
                providerMessage: $this->providerErrorMessage($e),
                previous: $e
            );
        }
    }

    /**
     * Fetches one raw message by exact IMAP UID without applying disposition.
     *
     * @param string $mailbox Mailbox or folder name.
     * @param int $uid IMAP UID to fetch.
     * @return RawMessage
     *
     * @throws FetchException When the message cannot be fetched.
     * @throws InvalidArgumentException When mailbox or UID is invalid.
     */
    public function fetchByUid(string $mailbox, int $uid): RawMessage
    {
        $mailbox = $this->normalizeMailbox($mailbox);
        $uid = $this->normalizeUid($uid);

        try {
            return $this->withSessionClient(function (WebklexClient $client) use (
                $mailbox,
                $uid
            ): RawMessage {
                $message = $this->messageByUid($client, $mailbox, $uid);

                return $this->toRawMessage($message, $mailbox, $uid);
            });
        } catch (FetchException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FetchException(
                \sprintf('Unable to fetch IMAP UID %d from mailbox "%s".', $uid, $mailbox),
                providerMessage: $this->providerErrorMessage($e),
                previous: $e
            );
        }
    }

    /**
     * Applies a post-fetch mailbox disposition to a previously fetched raw message.
     *
     * @param RawMessage $message Previously fetched IMAP message.
     * @param FetchDisposition $disposition Disposition to apply.
     * @return void
     *
     * @throws DispositionException When the mailbox or message cannot be resolved or mutated.
     * @throws InvalidArgumentException When the raw message lacks valid IMAP identity.
     */
    public function applyDisposition(RawMessage $message, FetchDisposition $disposition): void
    {
        $identity = $this->imapIdentity($message);
        $mailbox = $identity['mailbox'];
        $uid = $identity['uid'];

        try {
            $this->withSessionClient(function (WebklexClient $client) use (
                $mailbox,
                $uid,
                $disposition
            ): void {
                $source = $this->messageByUid($client, $mailbox, $uid);

                $this->applyMessageDisposition(
                    $source,
                    $disposition,
                    $mailbox,
                    $uid
                );
            });
        } catch (DispositionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DispositionException(
                \sprintf(
                    'Unable to apply IMAP disposition "%s" to UID %d in mailbox "%s".',
                    $disposition->action(),
                    $uid,
                    $mailbox
                ),
                providerMessage: $this->providerErrorMessage($e),
                previous: $e
            );
        }
    }

    /**
     * Ensures a mailbox exists and can be examined without mutating message state.
     *
     * Folder creation explicitly disables Webklex's optional expunge side effect.
     *
     * @param WebklexClient $client Connected client.
     * @param string $mailbox Normalized mailbox name.
     * @return void
     *
     * @throws Throwable When the mailbox cannot be created or examined.
     */
    private function ensureMailboxReady(WebklexClient $client, string $mailbox): void
    {
        $folder = $client->getFolder($mailbox);

        if ($folder === null) {
            try {
                $folder = $client->createFolder($mailbox, false);
            } catch (Throwable $e) {
                if (!$this->hasProviderResponseCode($e, 'ALREADYEXISTS')) {
                    throw $e;
                }

                $folder = $this->resolveExistingMailbox($client, $mailbox);

                if ($folder === null) {
                    throw $e;
                }
            }
        }

        $folder->examine();
    }

    /**
     * Resolves a mailbox after the provider reports that creation conflicts with an existing name.
     *
     * The provider has already established that the requested name collides with an existing mailbox,
     * so a case-insensitive fallback is safe here without imposing global case-folding semantics.
     *
     * @param WebklexClient $client Connected client.
     * @param string $mailbox Requested mailbox name.
     * @return WebklexFolder|null
     *
     * @throws Throwable When available folders cannot be listed.
     */
    private function resolveExistingMailbox(
        WebklexClient $client,
        string $mailbox
    ): ?WebklexFolder {
        $nameMatch = null;

        foreach ($client->getFolders(false, null, true) as $folder) {
            if (\strcasecmp($folder->full_name, $mailbox) === 0
                || \strcasecmp($folder->path, $mailbox) === 0) {
                return $folder;
            }

            if ($nameMatch === null && \strcasecmp($folder->name, $mailbox) === 0) {
                $nameMatch = $folder;
            }
        }

        return $nameMatch;
    }

    /**
     * Detects a standardized IMAP provider response code through the backend exception chain.
     *
     * @param Throwable $exception Backend exception.
     * @param string $code IMAP response code.
     * @return bool
     */
    private function hasProviderResponseCode(Throwable $exception, string $code): bool
    {
        $needle = '[' . \strtoupper($code) . ']';

        do {
            if (\str_contains(\strtoupper($exception->getMessage()), $needle)) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return false;
    }

    /**
     * Maps a backend readiness failure to the narrow package connection exception contract.
     *
     * @param string $message Package-level failure summary.
     * @param Throwable $exception Backend exception.
     * @return ConnectionException
     */
    private function connectionException(string $message, Throwable $exception): ConnectionException
    {
        $providerCode = $this->providerErrorCode($exception);
        $providerMessage = $this->providerErrorMessage($exception);

        if ($this->isAuthenticationFailure($exception)) {
            return new AuthenticationException(
                'IMAP authentication failed.',
                $providerCode,
                $providerMessage,
                $exception
            );
        }

        return new ConnectionException(
            $message,
            $providerCode,
            $providerMessage,
            $exception
        );
    }

    /**
     * Detects a Webklex authentication failure through the exception chain.
     *
     * @param Throwable $exception Backend exception.
     * @return bool
     */
    private function isAuthenticationFailure(Throwable $exception): bool
    {
        do {
            if ($exception instanceof WebklexAuthFailedException) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return false;
    }

    /**
     * Returns a provider code only when exposed by a Webklex backend exception.
     *
     * @param Throwable $exception Candidate backend exception.
     * @return string|null
     */
    private function providerErrorCode(Throwable $exception): ?string
    {
        if (!\str_starts_with($exception::class, 'Webklex\\PHPIMAP\\')) {
            return null;
        }

        return $exception->getCode() !== 0 ? (string) $exception->getCode() : null;
    }

    /**
     * Releases the reusable IMAP session owned by this fetcher.
     *
     * Disconnect is best-effort so completed mailbox operations are not reported as failed solely
     * because graceful connection cleanup failed. A later operation reconnects lazily.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $client = $this->client;
        $this->client = null;

        $this->disconnectClient($client);
    }

    /**
     * Releases the reusable IMAP session when the fetcher leaves scope.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Executes an operation on the reusable authenticated IMAP session.
     *
     * The first operation connects lazily. Webklex verifies the retained session as mailbox operations
     * execute and reconnects when necessary. Failed mutating operations are never replayed
     * automatically because their remote completion state may be ambiguous.
     *
     * @template T
     *
     * @param callable(WebklexClient): T $operation Connected client operation.
     * @return T
     *
     * @throws Throwable When connection or operation execution fails.
     */
    private function withSessionClient(callable $operation): mixed
    {
        return $operation($this->sessionClient());
    }

    /**
     * Executes one isolated connection operation and disconnects immediately afterward.
     *
     * Readiness probes use an isolated client because they are complete standalone operations and
     * must not leave an execution session open for a later polling run.
     *
     * @template T
     *
     * @param callable(WebklexClient): T $operation Connected client operation.
     * @return T
     *
     * @throws Throwable When connection or operation execution fails.
     */
    private function withIsolatedClient(callable $operation): mixed
    {
        $client = null;

        try {
            $client = $this->clientManager->make($this->webklexAccountConfig());
            $client->connect();

            return $operation($client);
        } finally {
            $this->disconnectClient($client);
        }
    }

    /**
     * Returns the reusable connected IMAP client for one fetcher execution scope.
     *
     * @return WebklexClient
     *
     * @throws Throwable When the initial connection cannot be established.
     */
    private function sessionClient(): WebklexClient
    {
        if ($this->client === null) {
            $client = $this->clientManager->make($this->webklexAccountConfig());
            $client->connect();
            $this->client = $client;

            return $client;
        }

        return $this->client;
    }

    /**
     * Disconnects a Webklex client without changing the completed operation outcome.
     *
     * @param WebklexClient|null $client Connected client when available.
     * @return void
     */
    private function disconnectClient(?WebklexClient $client): void
    {
        if ($client === null) {
            return;
        }

        try {
            $client->disconnect();
        } catch (Throwable) {
            // Deliberately ignored; the completed operation result is authoritative.
        }
    }

    /**
     * Resolves one provider message by mailbox and exact UID.
     *
     * @param WebklexClient $client Connected client.
     * @param string $mailbox Mailbox name.
     * @param int $uid Message UID.
     * @return WebklexMessage
     *
     * @throws RuntimeException When the mailbox or message cannot be resolved.
     */
    private function messageByUid(WebklexClient $client, string $mailbox, int $uid): WebklexMessage
    {
        $folder = $client->getFolder($mailbox);

        if ($folder === null) {
            throw new RuntimeException(
                \sprintf('IMAP mailbox "%s" could not be found.', $mailbox)
            );
        }

        return $folder
            ->messages()
            ->leaveUnread()
            ->getMessageByUid($uid);
    }

    /**
     * Sorts a selected message batch into oldest-to-newest processing order.
     *
     * @param iterable<WebklexMessage> $messages Selected Webklex messages.
     * @return array<int, array{uid: int, message: WebklexMessage}>
     *
     * @throws FetchException When a message UID cannot be read.
     */
    private function oldestToNewest(iterable $messages): array
    {
        /** @var array<int, WebklexMessage> $messagesByUid */
        $messagesByUid = [];

        foreach ($messages as $message) {
            $messagesByUid[$this->messageUid($message)] = $message;
        }

        \ksort($messagesByUid, \SORT_NUMERIC);

        $ordered = [];

        foreach ($messagesByUid as $uid => $message) {
            $ordered[] = [
                'uid' => $uid,
                'message' => $message,
            ];
        }

        return $ordered;
    }

    /**
     * Reads and validates the IMAP UID from a Webklex message.
     *
     * @param WebklexMessage $message Source message.
     * @return int
     *
     * @throws FetchException When the UID cannot be read or is invalid.
     */
    private function messageUid(WebklexMessage $message): int
    {
        try {
            /** @var mixed $uid */
            $uid = $message->getUid();
        } catch (Throwable $e) {
            throw new FetchException(
                'IMAP message UID could not be read.',
                providerMessage: $this->providerErrorMessage($e),
                previous: $e
            );
        }

        if (!\is_int($uid) && !\is_string($uid)) {
            throw new FetchException('IMAP message UID could not be read.');
        }

        try {
            return $this->normalizeUid($uid);
        } catch (InvalidArgumentException $e) {
            throw new FetchException(
                'IMAP message UID could not be read.',
                previous: $e
            );
        }
    }

    /**
     * Resolves the IMAP identity recorded on a fetched raw message.
     *
     * @param RawMessage $message Previously fetched IMAP message.
     * @return array{mailbox: string, uid: int}
     *
     * @throws InvalidArgumentException When IMAP identity is unavailable or invalid.
     */
    private function imapIdentity(RawMessage $message): array
    {
        $mailbox = $message->mailbox();
        $providerId = $message->providerId();

        if ($mailbox === null) {
            throw new InvalidArgumentException('Fetched IMAP message is missing mailbox context.');
        }

        if ($providerId === null) {
            throw new InvalidArgumentException('Fetched IMAP message is missing a valid UID.');
        }

        try {
            $uid = $this->normalizeUid($providerId);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                'Fetched IMAP message is missing a valid UID.',
                previous: $e
            );
        }

        return [
            'mailbox' => $mailbox,
            'uid' => $uid,
        ];
    }

    /**
     * Normalizes a non-zero unsigned 32-bit IMAP UID.
     *
     * @param int|string $uid Candidate UID.
     * @return int
     *
     * @throws InvalidArgumentException When the UID is invalid.
     */
    private function normalizeUid(int|string $uid): int
    {
        if (\is_int($uid)) {
            if ($uid < 1 || $uid > self::MAX_UID) {
                throw new InvalidArgumentException('IMAP UID is invalid.');
            }

            return $uid;
        }

        if (\preg_match('/\A[1-9][0-9]*\z/', $uid) !== 1) {
            throw new InvalidArgumentException('IMAP UID is invalid.');
        }

        $length = \strlen($uid);

        if (
            $length > 10
            || ($length === 10 && \strcmp($uid, (string) self::MAX_UID) > 0)
        ) {
            throw new InvalidArgumentException('IMAP UID is invalid.');
        }

        return (int) $uid;
    }

    /**
     * Applies a mailbox disposition to a resolved Webklex message.
     *
     * Move intentionally marks the source message as read before relocation so a failed move leaves
     * an observable indication that processing reached the post-fetch stage.
     *
     * @param WebklexMessage $message Source message.
     * @param FetchDisposition $disposition Mailbox disposition.
     * @param string $mailbox Source mailbox.
     * @param int $uid Source message UID.
     * @return void
     *
     * @throws DispositionException When the disposition cannot be applied.
     */
    private function applyMessageDisposition(
        WebklexMessage $message,
        FetchDisposition $disposition,
        string $mailbox,
        int $uid
    ): void {
        try {
            if ($disposition->shouldMarkRead()) {
                $result = $message->setFlag('Seen');

                if ($result === false) {
                    throw new DispositionException(
                        \sprintf('Unable to mark IMAP UID %d as read in mailbox "%s".', $uid, $mailbox)
                    );
                }

                return;
            }

            if ($disposition->shouldMove()) {
                $target = $disposition->mailbox();

                if ($target === null) {
                    throw new DispositionException('Move disposition is missing a target mailbox.');
                }

                $readResult = $message->setFlag('Seen');

                if ($readResult === false) {
                    throw new DispositionException(
                        \sprintf(
                            'Unable to mark IMAP UID %d as read before moving it from mailbox "%s".',
                            $uid,
                            $mailbox
                        )
                    );
                }

                $result = $message->move($target);

                if ($result === null) {
                    throw new DispositionException(
                        \sprintf(
                            'Unable to move IMAP UID %d from mailbox "%s" to "%s".',
                            $uid,
                            $mailbox,
                            $target
                        )
                    );
                }

                return;
            }

            if ($disposition->shouldDelete()) {
                $result = $message->delete(true);

                if ($result === false) {
                    throw new DispositionException(
                        \sprintf('Unable to delete IMAP UID %d from mailbox "%s".', $uid, $mailbox)
                    );
                }
            }
        } catch (DispositionException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DispositionException(
                \sprintf(
                    'Unable to apply IMAP disposition "%s" to UID %d in mailbox "%s".',
                    $disposition->action(),
                    $uid,
                    $mailbox
                ),
                providerMessage: $this->providerErrorMessage($e),
                previous: $e
            );
        }
    }

    /**
     * Converts a Webklex message into the package RawMessage primitive.
     *
     * @param WebklexMessage $message Source message.
     * @param string $mailbox Mailbox name.
     * @param int $uid Validated IMAP UID.
     * @return RawMessage
     *
     * @throws FetchException When the raw RFC/MIME content cannot be built.
     */
    private function toRawMessage(WebklexMessage $message, string $mailbox, int $uid): RawMessage
    {
        $header = $message->getHeader();
        $rawHeader = $header?->raw ?? null;

        if (!\is_string($rawHeader) || \trim($rawHeader) === '') {
            throw new FetchException('IMAP message header could not be read.');
        }

        $rawBody = $message->getRawBody();
        $content = $this->normalizeLineEndings($rawHeader)
            . "\r\n\r\n"
            . $this->normalizeLineEndings($rawBody);

        return new RawMessage(
            $content,
            providerId: (string) $uid,
            mailbox: $mailbox,
            receivedAt: $this->messageReceivedAt($message),
            size: $this->messageSize($message) ?? \strlen($content),
            flags: $this->messageFlags($message),
            meta: [
                'source' => 'imap',
                'backend' => 'webklex/php-imap',
            ]
        );
    }

    /**
     * Reads the message size when available.
     *
     * @param WebklexMessage $message Source message.
     * @return int|null
     */
    private function messageSize(WebklexMessage $message): ?int
    {
        try {
            $size = $message->size ?? null;

            if (\is_int($size)) {
                return $size;
            }

            if (\is_numeric($size)) {
                return (int) $size;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Reads the message received date when available.
     *
     * @param WebklexMessage $message Source message.
     * @return DateTimeImmutable|null
     */
    private function messageReceivedAt(WebklexMessage $message): ?DateTimeImmutable
    {
        try {
            $date = $message->date ?? null;

            if ($date instanceof DateTimeImmutable) {
                return $date;
            }

            if ($date instanceof \DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($date);
            }

            if (\is_string($date) && \trim($date) !== '') {
                return new DateTimeImmutable($date);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Reads normalized message flags when available.
     *
     * @param WebklexMessage $message Source message.
     * @return string[]
     */
    private function messageFlags(WebklexMessage $message): array
    {
        try {
            $flags = $message->getFlags();
            $normalized = [];

            foreach ($flags as $flag) {
                $value = $this->stringifyFlag($flag);

                if ($value !== null) {
                    $normalized[] = $value;
                }
            }

            return \array_values(\array_unique($normalized));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Converts one provider flag to a string value when possible.
     *
     * @param mixed $flag Provider flag.
     * @return string|null
     */
    private function stringifyFlag(mixed $flag): ?string
    {
        if (\is_string($flag)) {
            $value = $flag;
        } elseif (\is_object($flag) && \method_exists($flag, '__toString')) {
            $value = (string) $flag;
        } elseif (\is_object($flag) && \method_exists($flag, 'name')) {
            $value = (string) $flag->name();
        } else {
            return null;
        }

        $value = \trim($value, " \\\t\n\r\0\x0B");

        return $value !== '' ? $value : null;
    }

    /**
     * Normalize host values.
     *
     * @param string $host Candidate host.
     * @return string
     *
     * @throws InvalidArgumentException When host is invalid.
     */
    private function normalizeHost(string $host): string
    {
        $host = \trim($host);

        if ($host === '') {
            throw new InvalidArgumentException('IMAP host cannot be empty.');
        }

        return $host;
    }

    /**
     * Normalize port values.
     *
     * @param int $port Candidate port.
     * @return int
     *
     * @throws InvalidArgumentException When port is invalid.
     */
    private function normalizePort(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('IMAP port must be between 1 and 65535.');
        }

        return $port;
    }

    /**
     * Normalize encryption values.
     *
     * @param string|null $encryption Candidate encryption value.
     * @return string|null
     *
     * @throws InvalidArgumentException When encryption is invalid.
     */
    private function normalizeEncryption(?string $encryption): ?string
    {
        if ($encryption === null) {
            return null;
        }

        $encryption = \strtolower(\trim($encryption));

        if ($encryption === '') {
            return null;
        }

        if (!\in_array($encryption, ['ssl', 'tls', 'notls'], true)) {
            throw new InvalidArgumentException('IMAP encryption must be ssl, tls, notls, or null.');
        }

        return $encryption;
    }

    /**
     * Normalize timeout values.
     *
     * @param int $timeoutSeconds Candidate timeout.
     * @return int
     *
     * @throws InvalidArgumentException When timeout is invalid.
     */
    private function normalizeTimeout(int $timeoutSeconds): int
    {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('IMAP timeout must be greater than zero.');
        }

        return $timeoutSeconds;
    }

    /**
     * Normalize a mailbox name.
     *
     * @param string $mailbox Candidate mailbox.
     * @return string
     *
     * @throws InvalidArgumentException When mailbox is invalid.
     */
    private function normalizeMailbox(string $mailbox): string
    {
        $mailbox = \trim($mailbox);

        if ($mailbox === '') {
            throw new InvalidArgumentException('IMAP mailbox cannot be empty.');
        }

        return $mailbox;
    }

    /**
     * Normalizes line endings to CRLF.
     *
     * @param string $value Raw value.
     * @return string
     */
    private function normalizeLineEndings(string $value): string
    {
        $value = \str_replace("\r\n", "\n", $value);
        $value = \str_replace("\r", "\n", $value);

        return \str_replace("\n", "\r\n", $value);
    }

    /**
     * Builds the webklex/php-imap account configuration array.
     *
     * @return array<string, mixed>
     */
    private function webklexAccountConfig(): array
    {
        $username = '';
        $password = '';
        $authentication = null;

        if ($this->credentials instanceof PasswordCredentials) {
            $username = $this->credentials->username();
            $password = $this->credentials->password();
        } elseif ($this->credentials instanceof XOAuth2Credentials) {
            $username = $this->credentials->username();
            $password = $this->credentials->accessToken();
            $authentication = 'oauth';
        }

        return [
            'host' => $this->host,
            'port' => $this->port,
            'protocol' => 'imap',
            'encryption' => $this->encryption,
            'validate_cert' => $this->validateCertificate,
            'username' => $username,
            'password' => $password,
            'authentication' => $authentication,
            'timeout' => $this->timeoutSeconds,
        ];
    }

    /**
     * Returns a concise diagnostic only for exceptions raised by the IMAP backend.
     *
     * @param Throwable $exception Candidate backend exception.
     * @return string|null
     */
    private function providerErrorMessage(Throwable $exception): ?string
    {
        if (!\str_starts_with($exception::class, 'Webklex\\PHPIMAP\\')) {
            return null;
        }

        $message = \trim($exception->getMessage());

        if (\strlen($message) > 2000) {
            return \substr($message, 0, 2000);
        }

        return $message !== '' ? $message : null;
    }
}