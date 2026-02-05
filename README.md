# 📬 osticket/mail

**osticket/mail** is a framework-agnostic PHP mail library that provides **RFC-compliant message construction**, **transport-level delivery**, and **mail protocol primitives** primarily used by osTicket’s outbound and inbound mail systems.

It is designed as a modern, composable replacement for legacy mail libraries (e.g. Laminas Mail), with a strict separation between **protocol-level concerns** and **application-level orchestration**.

This library contains **no framework assumptions** and no application-specific logic.

## Goals & Design Principles

* **Protocol-first**: RFC correctness and predictable message output
* **Backend-agnostic**: usable outside Laravel or osTicket
* **Policy-free**: no transport selection, retries, queues, logging, or configuration ownership
* **Composable**: exposes message, envelope, transport, fetch, and parser primitives that consumers can assemble into their own send/fetch workflows
* **Convenient**: provides package-level helpers for common message composition flows
* **Extensible**: clear extension points for DKIM, DMARC, AV, etc.

## Features

### RFC-Compliant Message Building

* Text and HTML bodies
* Multipart alternative / mixed / related output through Symfony Mime
* Attachments and inline parts
* Header normalization and validation
* Deterministic Message-ID generation once prepared
* Convenience setters for From, Sender, Reply-To, recipients, subject, bodies, attachments, and headers

### Transport Implementations

* **SMTP transport**
  * Plain auth
  * OAuth2 / XOAUTH2
  * STARTTLS enforcement and validation
  * Non-delivery connection verification
* **Sendmail transport**
* Transport-level result objects with provider ID, provider codes/messages, and small safe diagnostics

### Protocol & Value Primitives

* Address / mailbox value objects
* Explicit delivery envelope handling (MAIL FROM / RCPT TO)
* Message and body primitives
* Stable low-level delivery result representation
* Raw inbound message, fetch-window, and disposition primitives

### Inbound Fetch & Parsing Primitives

* Raw RFC/MIME message container
* Explicit bounded mailbox-window selection
* IMAP fetcher that wraps `webklex/php-imap` for standard mailbox retrieval
* IMAP connection and mailbox-readiness verification with idempotent folder provisioning
* Explicit post-fetch dispositions for marking read, moving, or deleting processed IMAP messages
* Parsed message result object
* Raw message parser backed by `zbateson/mail-mime-parser` for decoded headers, text/HTML body extraction, and parsed attachment metadata/content

### Extension Points

* DKIM signing hooks
* DMARC policy hooks
* Anti-virus scanning hooks

Interfaces only — no registration, discovery, or execution logic.

## Explicit Non-Goals

This library **does NOT**:
* Select which transport to use
* Register transports or plugins
* Handle retries, queues, logging, or metrics
* Perform HTML mutation or CID rewriting
* Contain UI or admin functionality
* Depend on Laravel or a container

All orchestration, policy, and application logic lives in the application.

## Installation

Install via Composer:

```bash
composer require osticket/mail
```

## Basic Usage

The package gives simple callers a convenient message-building flow while still preserving explicit transport envelope semantics.

```php
use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\{
    AddressList,
    EmailAddress,
    Envelope
};
use Osticket\Mail\Transport\SmtpTransport;

// Build message
$message = (new Message())
    ->setFrom(new EmailAddress('noreply@example.com', 'osTicket'))
    ->addTo(new EmailAddress('user@example.com', 'User'))
    ->setSubject('Hello')
    ->setTextBody('Hello world')
    ->setHtmlBody('<p>Hello world</p>')
    ->prepare();

// Prepare transport
$transport = new SmtpTransport('smtp.example.com', 587);

// Send
$result = $transport->send(
    Envelope::fromMessage($message),
    $message->toString()
);
```

`Envelope::fromMessage()` is a convenience helper for simple package usage. It uses the message `From` address as the default envelope sender and combines `To`, `Cc`, and `Bcc` recipients into the envelope recipient list.

Applications with bounce-address, tenant, mailbox, spoofing, or delivery-policy requirements should construct the envelope explicitly:

```php
$envelope = new Envelope(
    new EmailAddress('bounce+ticket-123@example.com'),
    $message->allRecipients()
);

$result = $transport->send($envelope, $message->toString());
```

## Connection Readiness

SMTP and IMAP expose protocol-level readiness operations so consumers can validate effective connection settings without duplicating provider construction outside the package. Readiness methods return `void` on success and throw package connection exceptions on failure.

```php
use Osticket\Mail\Exceptions\{AuthenticationException, ConnectionException};
use Osticket\Mail\Transport\SmtpTransport;

$transport = new SmtpTransport('smtp.example.com', 587);

try {
    $transport->verifyConnection();
} catch (AuthenticationException $e) {
    // The provider reliably rejected authentication.
} catch (ConnectionException $e) {
    // The SMTP session could not be established or verified.
}
```

SMTP verification establishes the configured session, including TLS and authentication when supplied, then disconnects without issuing `MAIL FROM`, `RCPT TO`, or `DATA`. A successful unauthenticated session is valid when the configured relay permits it. Message submission remains a separate operation.

## IMAP Fetching

The package wraps `webklex/php-imap` to retrieve raw RFC/MIME messages from a concrete IMAP mailbox while leaving account selection, token refresh, persistence, deduplication, processing policy, and retries to the consumer.

```php
use Osticket\Mail\Auth\Credentials\PasswordCredentials;
use Osticket\Mail\Fetch\{FetchDisposition, FetchWindow, ImapFetcher};

$fetcher = new ImapFetcher(
    host: 'imap.example.com',
    port: 993,
    encryption: 'ssl',
    credentials: new PasswordCredentials('user@example.com', 'secret')
);

$disposition = FetchDisposition::moveTo('INBOX.Processed');

// Connection-only verification does not select or query a mailbox.
$fetcher->verifyConnection();

// Full readiness may verify only the source mailbox when no mutation is configured.
$fetcher->verifyMailboxReadiness('INBOX');

// Move readiness also verifies or provisions the configured destination.
$fetcher->verifyMailboxReadiness('INBOX', $disposition);

try {
    $messages = $fetcher->fetch(
        mailbox: 'INBOX',
        limit: 50,
        window: $disposition->fetchWindow()
    );

    foreach ($messages as $message) {
        // Normalize, deduplicate, route, and persist the message first.

        $fetcher->applyDisposition($message, $disposition);
    }
} finally {
    $fetcher->disconnect();
}
```

Retrieval and mailbox mutation are separate operations. `fetch()` and `fetchByUid()` never apply mark-read, move, or delete policy. Retrieval uses the backend's peek behavior to preserve read state where supported, but exact `\Seen` behavior can vary by IMAP provider and backend implementation. Read/unread state is not a processing ledger.

Normal fetch and disposition operations reuse one authenticated IMAP session for the lifetime of the `ImapFetcher` instance. Webklex checks the retained connection as mailbox operations execute and reconnects when the session has been lost. The package does not replay a failed mutating operation automatically because the provider may already have completed that mutation before the connection failure became visible. Consumers should keep one fetcher for a bounded mailbox execution and call `disconnect()` when that execution ends. Disconnect is best-effort, and the fetcher also disconnects when it leaves scope.

`verifyConnection()` establishes and authenticates the IMAP session without selecting, querying, or creating a mailbox. `verifyMailboxReadiness()` performs a full non-message-mutating readiness check: it resolves the configured source mailbox, creates it when missing, and does the same for a move destination when a disposition requiring one is supplied. Passing no disposition verifies only source-mailbox readiness, which supports consumers whose retained-mail policy performs no message mutation. If a provider rejects mailbox creation with the standardized `ALREADYEXISTS` response, readiness re-resolves the provider-listed mailbox so case-preserving/case-insensitive backends can return their canonical existing name without imposing case-folding on all IMAP servers. Folder creation explicitly disables Webklex's optional expunge behavior. Readiness may therefore mutate mailbox structure, but it never marks, moves, deletes, or expunges messages.

`FetchWindow::LATEST` selects the newest bounded window, while `FetchWindow::OLDEST` selects the oldest bounded batch. Every selected batch is returned oldest-to-newest. Destructive dispositions recommend the oldest window so successful messages naturally leave the source folder; retained messages recommend the latest window so old retained mail does not permanently block newer mail.

```php
$readDisposition = FetchDisposition::markRead();
$moveDisposition = FetchDisposition::moveTo('INBOX.Processed');
$deleteDisposition = FetchDisposition::delete();
```

Move intentionally marks the source message as read before attempting relocation. If relocation fails, the message can remain in the source mailbox as read, providing an operational indication that processing reached post-fetch handling.

Delete delegates to normal IMAP delete-and-expunge behavior. Expunge can permanently remove other messages already marked `\Deleted` in the selected mailbox. Move mechanics, including provider fallback behavior, are owned by the IMAP backend.

Each IMAP `RawMessage` retains its source mailbox and true UID through `mailbox()` and `providerId()`. Disposition resolves the exact source message by mailbox and UID on the reusable execution session, reconnecting first when Webklex determines that session has been lost. The package does not retain live message objects or use shifting message sequence positions as delayed identity.

The returned message batch is bounded by `limit`. The underlying backend may still inspect a larger candidate UID set while selecting that window. Repeated retrieval is expected when messages remain in the source folder; consumers own deduplication and exactly-once processing policy.

Transport selection, retries, lifecycle events, result normalization, and application persistence remain intentionally **out of scope** for this package.

## Message API Notes

`Message` supports both constructor seeding and incremental composition:

```php
$message = new Message(
    new EmailAddress('agent@example.com', 'Support Agent'),
    AddressList::fromOne(new EmailAddress('user@example.com')),
    'Ticket reply'
);
```

or:

```php
$message = (new Message())
    ->setFrom(new EmailAddress('agent@example.com', 'Support Agent'))
    ->setSender(new EmailAddress('system@example.com'))
    ->setReplyTo(new EmailAddress('support@example.com'))
    ->addTo(new EmailAddress('user@example.com'))
    ->addCc(new EmailAddress('manager@example.com'))
    ->addBcc(new EmailAddress('audit@example.com'))
    ->setSubject('Ticket reply')
    ->setTextBody('Plain text body')
    ->setHtmlBody('<p>HTML body</p>');
```

`toString()` renders the prepared message as an RFC 5322 / MIME string suitable for transport. Calling `toString()` prepares the message automatically when needed, but callers may call `prepare()` explicitly when they want to finalize the message before handing it to transport helpers.

## Internal Architecture

* **Message**
  * Owns RFC-compliant message construction
  * Assigns Message-ID
  * Produces final RFC822/MIME output
* **Support**
  * Owns package-level value objects such as `EmailAddress`, `AddressList`, `Envelope`, `Attachment`, `InlineImage`, and `DeliveryResult`
* **Transport**
  * Executes protocol-level delivery using explicit `Envelope` + finalized RFC822 string
  * Returns a low-level `DeliveryResult` or throws a structured transport exception
* **Fetch**
  * Owns raw inbound message, fetch-window, and disposition primitives
  * Provides a Webklex-backed IMAP fetcher and raw inbound message primitives
* **Parser**
  * Converts raw inbound message content into structured parsed message results
  * Keeps provider/API-specific acquisition in plugin adapters outside the core package
* **Extensions**
  * Interfaces only; designed to be invoked by the application layer

## Current Status & TODOs

The following areas are intentionally deferred and tracked explicitly:

### 🚧 Pending / In Progress

* **IMAP hardening**
  * Broader provider fixture coverage for UID, read-state, and disposition behavior
* **Raw MIME parsing**
  * MIME part tree exposure remains deferred
  * Streaming attachment extraction remains deferred

These will be implemented incrementally and removed from this list as they land.

## Testing

The library includes a focused test suite covering:
* Message construction and RFC output
* Transport behavior and failure modes
* SMTP auth flows (including OAuth2)
* Value object correctness
* Privacy and idempotency guarantees for rendered messages

Run tests with:

```bash
composer test
```

Or directly:

```bash
./vendor/bin/phpunit
```

### Live IMAP Integration Tests

Live IMAP behavior is covered by an opt-in integration suite that runs against a disposable fixture mailbox.

Create a local environment file from the committed example:

```bash
cp .env.live-imap.example .env.live-imap
```

Configure the mailbox connection values and fixture UIDs in `.env.live-imap`, then enable the suite:

```dotenv
OSTICKET_MAIL_IMAP_INTEGRATION=1
```

Load the environment and run the integration suite:

```bash
set -a
. ./.env.live-imap
set +a

composer test-integration
```

The integration suite performs real mailbox operations.

The mark-read, move, and delete tests intentionally mutate mailbox state. Use a disposable mailbox and assign a distinct fixture UID to each mutation test. The readiness provisioning test may create the mailboxes configured by `OSTICKET_MAIL_IMAP_READINESS_MAILBOX` and `OSTICKET_MAIL_IMAP_READINESS_MOVE_MAILBOX`; repeated runs reuse those folders idempotently.

The normal test suite does not run live IMAP tests:

```bash
composer test
```

## Contributing

This library follows strict architectural boundaries.

Contributions should:
* Avoid introducing application-level concerns
* Include tests for any new protocol or transport behavior
* Preserve strict typing in package PHP files

## License

MIT License. See [LICENSE](LICENSE) for details.