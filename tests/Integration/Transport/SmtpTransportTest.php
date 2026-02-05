<?php

declare(strict_types=1);

namespace Osticket\Mail\Tests\Integration\Transport;

use Osticket\Mail\Auth\Credentials\PasswordCredentials;
use Osticket\Mail\Exceptions\{AuthenticationException, ConnectionException};
use Osticket\Mail\Support\AddressList;
use Osticket\Mail\Support\EmailAddress;
use Osticket\Mail\Support\Envelope;
use Osticket\Mail\Transport\Exceptions\TransportException;
use Osticket\Mail\Transport\SmtpTransport;
use PHPUnit\Framework\TestCase;

final class SmtpTransportTest extends TestCase
{
    public function test_smtp_opportunistic_tls_succeeds_when_no_starttls_advertised(): void
    {
        $server = FakeSmtpServer::start(advertiseStartTls: false);
        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: null,
                autoTls: true,
                requireTls: null
            );

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
            self::assertSame('smtp', $result->meta()['transport'] ?? null);
        } finally {
            $server->stop();
        }
    }

    public function test_forced_tls_fails_when_starttls_not_advertised(): void
    {
        $server = FakeSmtpServer::start(advertiseStartTls: false);

        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: null,
                autoTls: true,
                requireTls: true
            );

            $envelope = new Envelope(
                new EmailAddress('from@example.com'),
                new AddressList([new EmailAddress('to@example.com')])
            );

            $rfc822 = "From: from@example.com\r\nTo: to@example.com\r\nSubject: Test\r\n\r\nHello\r\n";

            try {
                $transport->send($envelope, $rfc822);
                self::fail('Expected TransportException to be thrown.');
            } catch (TransportException $e) {
                self::assertNotSame('', \trim($e->getMessage()));
                self::assertTrue(
                    $e->providerCode() !== null || $e->providerMessage() !== null,
                    'Expected providerCode or providerMessage to be populated'
                );
            }
        } finally {
            $server->stop();
        }
    }

    public function test_verify_connection_establishes_no_auth_session_without_sending_message(): void
    {
        $server = FakeSmtpServer::start(advertiseStartTls: false);

        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: null,
                autoTls: true,
                requireTls: false
            );

            $transport->verifyConnection();
            $commands = $server->commands();

            self::assertTrue($this->containsCommand($commands, 'EHLO ') || $this->containsCommand($commands, 'HELO '));
            self::assertFalse($this->containsCommand($commands, 'AUTH '));
            self::assertFalse($this->containsCommand($commands, 'MAIL FROM:'));
            self::assertFalse($this->containsCommand($commands, 'RCPT TO:'));
            self::assertFalse($this->containsCommand($commands, 'DATA'));
        } finally {
            $server->stop();
        }
    }

    public function test_verify_connection_authenticates_without_sending_message(): void
    {
        $server = FakeSmtpServer::start(
            advertiseStartTls: false,
            advertiseAuth: true,
            acceptAuth: true
        );

        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: new PasswordCredentials('user@example.com', 'secret'),
                autoTls: false,
                requireTls: false
            );

            $transport->verifyConnection();
            $commands = $server->commands();

            self::assertTrue($this->containsCommand($commands, 'AUTH LOGIN'));
            self::assertFalse($this->containsCommand($commands, 'MAIL FROM:'));
            self::assertFalse($this->containsCommand($commands, 'RCPT TO:'));
            self::assertFalse($this->containsCommand($commands, 'DATA'));
        } finally {
            $server->stop();
        }
    }

    public function test_verify_connection_classifies_smtp_authentication_rejection(): void
    {
        $server = FakeSmtpServer::start(
            advertiseStartTls: false,
            advertiseAuth: true,
            acceptAuth: false
        );

        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: new PasswordCredentials('user@example.com', 'wrong-secret'),
                autoTls: false,
                requireTls: false
            );

            try {
                $transport->verifyConnection();
                self::fail('Expected AuthenticationException to be thrown.');
            } catch (AuthenticationException $e) {
                self::assertSame('535', $e->providerCode());
                self::assertNotNull($e->providerMessage());
            }
        } finally {
            $server->stop();
        }
    }

    public function test_verify_connection_classifies_required_tls_failure_as_connection_failure(): void
    {
        $server = FakeSmtpServer::start(advertiseStartTls: false);

        try {
            $transport = new SmtpTransport(
                host: '127.0.0.1',
                port: $server->port(),
                encryption: null,
                timeoutSeconds: 5,
                credentials: null,
                autoTls: true,
                requireTls: true
            );

            $this->expectException(ConnectionException::class);
            $this->expectExceptionMessage('SMTP connection verification failed.');

            $transport->verifyConnection();
        } finally {
            $server->stop();
        }
    }

    /**
     * Determine whether a recorded SMTP command begins with the expected command.
     *
     * @param string[] $commands Recorded SMTP commands.
     * @param string $expected Expected command prefix.
     * @return bool
     */
    private function containsCommand(array $commands, string $expected): bool
    {
        foreach ($commands as $command) {
            if (\str_starts_with($command, $expected)) {
                return true;
            }
        }

        return false;
    }
}

/**
 * One-shot SMTP server for integration tests.
 */
final class FakeSmtpServer
{
    private string $scriptPath;
    private string $transcriptPath;
    private $proc;
    private $pipes;
    private int $port;

    /**
     * Store a running fake SMTP server process.
     *
     * @param string $scriptPath Temporary server script path.
     * @param string $transcriptPath Temporary SMTP transcript path.
     * @param resource $proc Server process resource.
     * @param array<int, resource> $pipes Process pipes.
     * @param int $port Bound TCP port.
     * @return void
     */
    private function __construct(
        string $scriptPath,
        string $transcriptPath,
               $proc,
        array $pipes,
        int $port
    ) {
        $this->scriptPath = $scriptPath;
        $this->transcriptPath = $transcriptPath;
        $this->proc = $proc;
        $this->pipes = $pipes;
        $this->port = $port;
    }

    /**
     * Start a one-shot SMTP server with optional STARTTLS and LOGIN advertisement.
     *
     * @param bool $advertiseStartTls Whether EHLO advertises STARTTLS.
     * @param bool $advertiseAuth Whether EHLO advertises AUTH LOGIN.
     * @param bool $acceptAuth Whether LOGIN authentication succeeds.
     * @return self
     */
    public static function start(
        bool $advertiseStartTls,
        bool $advertiseAuth = false,
        bool $acceptAuth = true
    ): self {
        $script = <<<'PHP'
<?php
$advertiseStartTls = getenv('FAKE_SMTP_STARTTLS') === '1';
$advertiseAuth = getenv('FAKE_SMTP_AUTH') === '1';
$acceptAuth = getenv('FAKE_SMTP_AUTH_ACCEPT') === '1';
$transcript = getenv('FAKE_SMTP_TRANSCRIPT');

$record = static function (string $command) use ($transcript): void {
    if (is_string($transcript) && $transcript !== '') {
        file_put_contents($transcript, $command . "\n", FILE_APPEND);
    }
};

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "bind failed: $errstr\n");
    exit(2);
}

$addr = stream_socket_get_name($server, false);
$port = (int) substr($addr, strrpos($addr, ':') + 1);

echo $port . "\n";
flush();

$conn = @stream_socket_accept($server, 10);
if (!$conn) {
    exit(3);
}

fwrite($conn, "220 fake-smtp\r\n");
$authState = null;

while (!feof($conn)) {
    $line = fgets($conn);
    if ($line === false) {
        break;
    }

    $cmd = rtrim($line, "\r\n");
    $record($cmd);

    if ($authState === 'username') {
        $authState = 'password';
        fwrite($conn, "334 UGFzc3dvcmQ6\r\n");
        continue;
    }

    if ($authState === 'password') {
        $authState = null;
        fwrite(
            $conn,
            $acceptAuth
                ? "235 2.7.0 Authentication successful\r\n"
                : "535 5.7.8 Authentication credentials invalid\r\n"
        );
        continue;
    }

    if (stripos($cmd, 'EHLO ') === 0 || stripos($cmd, 'HELO ') === 0) {
        $capabilities = [];

        if ($advertiseStartTls) {
            $capabilities[] = 'STARTTLS';
        }

        if ($advertiseAuth) {
            $capabilities[] = 'AUTH LOGIN';
        }

        fwrite($conn, "250-fake\r\n");

        foreach ($capabilities as $capability) {
            fwrite($conn, "250-{$capability}\r\n");
        }

        fwrite($conn, "250 OK\r\n");
        continue;
    }

    if (strcasecmp($cmd, 'AUTH LOGIN') === 0) {
        $authState = 'username';
        fwrite($conn, "334 VXNlcm5hbWU6\r\n");
        continue;
    }

    if (stripos($cmd, 'MAIL FROM:') === 0) {
        fwrite($conn, "250 OK\r\n");
        continue;
    }

    if (stripos($cmd, 'RCPT TO:') === 0) {
        fwrite($conn, "250 OK\r\n");
        continue;
    }

    if (strcasecmp($cmd, 'DATA') === 0) {
        fwrite($conn, "354 End data with <CR><LF>.<CR><LF>\r\n");

        while (!feof($conn)) {
            $data = fgets($conn);
            if ($data === false) {
                break;
            }

            if (rtrim($data, "\r\n") === '.') {
                break;
            }
        }

        fwrite($conn, "250 OK queued as FAKE123\r\n");
        continue;
    }

    if (strcasecmp($cmd, 'RSET') === 0) {
        fwrite($conn, "250 OK\r\n");
        continue;
    }

    if (strcasecmp($cmd, 'QUIT') === 0) {
        fwrite($conn, "221 Bye\r\n");
        break;
    }

    fwrite($conn, "250 OK\r\n");
}

fclose($conn);
fclose($server);
PHP;

        $path = \tempnam(\sys_get_temp_dir(), 'fake-smtp-');
        $transcriptPath = \tempnam(\sys_get_temp_dir(), 'fake-smtp-transcript-');

        if ($path === false || $transcriptPath === false) {
            throw new \RuntimeException('Failed to create temporary SMTP files.');
        }

        \file_put_contents($path, $script);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = \array_merge($_ENV, [
            'FAKE_SMTP_STARTTLS' => $advertiseStartTls ? '1' : '0',
            'FAKE_SMTP_AUTH' => $advertiseAuth ? '1' : '0',
            'FAKE_SMTP_AUTH_ACCEPT' => $acceptAuth ? '1' : '0',
            'FAKE_SMTP_TRANSCRIPT' => $transcriptPath,
        ]);

        $proc = \proc_open([PHP_BINARY, $path], $descriptors, $pipes, null, $env);
        if (!\is_resource($proc)) {
            @\unlink($path);
            @\unlink($transcriptPath);
            throw new \RuntimeException('Failed to start fake SMTP server process.');
        }

        \stream_set_timeout($pipes[1], 5);
        $line = \fgets($pipes[1]);
        if ($line === false) {
            $stderr = \stream_get_contents($pipes[2]) ?: '';
            \proc_terminate($proc);
            \proc_close($proc);
            @\unlink($path);
            @\unlink($transcriptPath);
            throw new \RuntimeException('Fake SMTP server did not report a port. ' . $stderr);
        }

        return new self($path, $transcriptPath, $proc, $pipes, (int) \trim($line));
    }

    /**
     * Return the bound SMTP port.
     *
     * @return int
     */
    public function port(): int
    {
        return $this->port;
    }

    /**
     * Return commands received by the fake SMTP server.
     *
     * @return string[]
     */
    public function commands(): array
    {
        $contents = @\file_get_contents($this->transcriptPath);

        if (!\is_string($contents) || \trim($contents) === '') {
            return [];
        }

        return \array_values(\array_filter(\preg_split('/\R/', \trim($contents)) ?: []));
    }

    /**
     * Stop the fake SMTP server and remove temporary files.
     *
     * @return void
     */
    public function stop(): void
    {
        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                @\fclose($pipe);
            }
        }

        if (\is_resource($this->proc)) {
            @\proc_terminate($this->proc);
            @\proc_close($this->proc);
        }

        @\unlink($this->scriptPath);
        @\unlink($this->transcriptPath);
    }
}