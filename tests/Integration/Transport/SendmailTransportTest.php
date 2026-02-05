<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Integration\Transport;

use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use Osticket\Mail\Transport\Exceptions\TransportException;
use Osticket\Mail\Transport\SendmailTransport;
use PHPUnit\Framework\TestCase;

final class SendmailTransportTest extends TestCase
{
    public function test_sendmail_bs_success(): void
    {
        $fake = FakeSendmailBs::create(shouldFailRcpt: false);

        try {
            $transport = new SendmailTransport($fake->command());

            $envelope = new Envelope(
                new EmailAddress('from@example.com'),
                new AddressList([new EmailAddress('to@example.com')])
            );

            $rfc822 = "From: from@example.com\r\nTo: to@example.com\r\nSubject: Test\r\n\r\nHello\r\n";

            $result = $transport->send($envelope, $rfc822);

            $providerMessageId = $result->providerMessageId();

            self::assertTrue(
                $providerMessageId === null || $providerMessageId !== '',
                'providerMessageId must be null or a non-empty string'
            );

            self::assertTrue($result->sentSuccessfully());
            self::assertSame('sendmail', $result->meta()['transport'] ?? null);
        } finally {
            $fake->cleanup();
        }
    }

    public function test_sendmail_bs_failure_is_wrapped(): void
    {
        $fake = FakeSendmailBs::create(shouldFailRcpt: true);

        try {
            $transport = new SendmailTransport($fake->command());

            $envelope = new Envelope(
                new EmailAddress('from@example.com'),
                new AddressList([new EmailAddress('to@example.com')])
            );

            $rfc822 = "From: from@example.com\r\nTo: to@example.com\r\nSubject: Test\r\n\r\nHello\r\n";

            try {
                $transport->send($envelope, $rfc822);
                self::fail('Expected TransportException to be thrown.');
            } catch (TransportException $e) {
                self::assertNotSame('', trim($e->getMessage()));

                // At least one provider diagnostic must be present
                self::assertTrue(
                    $e->providerCode() !== null || $e->providerMessage() !== null,
                    'Expected providerCode or providerMessage to be populated'
                );
            }
        } finally {
            $fake->cleanup();
        }
    }
}

final class FakeSendmailBs
{
    private string $path;

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function create(bool $shouldFailRcpt): self
    {
        $script = <<<'PHP'
#!/usr/bin/env php
<?php
$failRcpt = getenv('FAKE_SENDMAIL_FAIL_RCPT') === '1';

function reply(string $line): void {
    echo $line . "\r\n";
    flush();
}

reply("220 fake-sendmail");

while (($line = fgets(STDIN)) !== false) {
    $cmd = rtrim($line, "\r\n");

    if (stripos($cmd, 'EHLO ') === 0 || stripos($cmd, 'HELO ') === 0) {
        reply("250-fake");
        reply("250 OK");
        continue;
    }

    if (stripos($cmd, 'MAIL FROM:') === 0) {
        reply("250 OK");
        continue;
    }

    if (stripos($cmd, 'RCPT TO:') === 0) {
        if ($failRcpt) {
            reply("550 No such user");
        } else {
            reply("250 OK");
        }
        continue;
    }

    if (strcasecmp($cmd, 'DATA') === 0) {
        reply("354 End data with <CR><LF>.<CR><LF>");
        while (($d = fgets(STDIN)) !== false) {
            if (rtrim($d, "\r\n") === '.') break;
        }
        reply("250 OK queued as FAKE-SENDMAIL-1");
        continue;
    }

    if (strcasecmp($cmd, 'QUIT') === 0) {
        reply("221 Bye");
        exit(0);
    }

    reply("250 OK");
}

exit(0);
PHP;

        $path = tempnam(sys_get_temp_dir(), 'fake-sendmail-');
        if ($path === false) {
            throw new \RuntimeException('Failed to create temp file.');
        }

        file_put_contents($path, $script);
        chmod($path, 0755);

        putenv('FAKE_SENDMAIL_FAIL_RCPT=' . ($shouldFailRcpt ? '1' : '0'));

        return new self($path);
    }

    public function command(): string
    {
        // Symfony SendmailTransport expects a command string; -bs requests SMTP mode.
        return $this->path . ' -bs';
    }

    public function cleanup(): void
    {
        @unlink($this->path);
        putenv('FAKE_SENDMAIL_FAIL_RCPT');
    }
}
