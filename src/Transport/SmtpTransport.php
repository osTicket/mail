<?php

declare(strict_types=1);

namespace Osticket\Mail\Transport;

use Osticket\Mail\Auth\Credentials\{
    PasswordCredentials,
    AuthCredentials,
    XOAuth2Credentials
};
use Osticket\Mail\Exceptions\{AuthenticationException, ConnectionException};
use Osticket\Mail\Support\{DeliveryResult, Envelope};
use Osticket\Mail\Transport\Contracts\TransportContract;
use Osticket\Mail\Transport\Exceptions\TransportException;
use Symfony\Component\Mailer\Envelope as SymfonyEnvelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mime\{Address, RawMessage};
use Throwable;

final class SmtpTransport implements TransportContract
{
    private string $host;
    private int $port;
    private ?string $encryption;
    private int $timeoutSeconds;

    private ?AuthCredentials $credentials;

    private bool $autoTls;
    private ?bool $requireTls;

    /**
     * Sends RFC822 messages over SMTP by delegating protocol semantics to Symfony Mailer.
     *
     * TLS rule: ports 465 and 587 require TLS; all other ports are opportunistic.
     *
     * @param string $host SMTP host.
     * @param int $port SMTP port.
     * @param string|null $encryption SMTP encryption mode (e.g. "tls", "ssl", or null).
     * @param int $timeoutSeconds Connection/IO timeout in seconds.
     * @param AuthCredentials|null $credentials Optional SMTP authentication credentials.
     * @param bool $autoTls When true, opportunistically attempts STARTTLS when available (when not requiring TLS).
     * @param bool|null $requireTls When null, computed from port (true for 465/587); when true, TLS is mandatory.
     * @return void
     */
    public function __construct(
        string $host,
        int $port = 25,
        ?string $encryption = null,
        int $timeoutSeconds = 30,
        ?AuthCredentials $credentials = null,
        bool $autoTls = true,
        ?bool $requireTls = null
    ) {
        $this->host = $host;
        $this->port = $port;

        // If the caller uses SMTPS (465) and doesn't specify encryption, default to implicit TLS.
        if ($port === 465 && $encryption === null) {
            $encryption = 'ssl';
        }

        $this->encryption = $encryption;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->credentials = $credentials;

        $this->autoTls = $autoTls;
        $this->requireTls = $requireTls;
    }

    /**
     * Verifies that the configured SMTP session can be established without sending a message.
     *
     * @return void
     *
     * @throws AuthenticationException When SMTP authentication is reliably rejected.
     * @throws ConnectionException When the SMTP session cannot be established.
     */
    public function verifyConnection(): void
    {
        $transport = $this->createTransport();

        try {
            $transport->start();
        } catch (TransportExceptionInterface $e) {
            $providerCode = $e->getCode() !== 0 ? (string) $e->getCode() : null;
            $providerMessage = $this->trimProviderMessage($e->getMessage());

            if ($this->isAuthenticationFailure($e)) {
                throw new AuthenticationException(
                    'SMTP authentication failed.',
                    $providerCode,
                    $providerMessage,
                    $e
                );
            }

            throw new ConnectionException(
                'SMTP connection verification failed.',
                $providerCode,
                $providerMessage,
                $e
            );
        } finally {
            $this->disconnectTransport($transport);
        }
    }

    /**
     * Delivers a pre-rendered RFC822 message using an explicit envelope for MAIL FROM / RCPT TO.
     *
     * @param Envelope $envelope Envelope sender/recipients for delivery.
     * @param string $rfc822 Fully rendered RFC822 message string (authoritative; no re-rendering here).
     * @return DeliveryResult
     *
     * @throws TransportException When the SMTP transaction fails.
     */
    public function send(Envelope $envelope, string $rfc822): DeliveryResult
    {
        if (\trim($rfc822) === '') {
            throw new TransportException('Empty RFC822 payload.');
        }

        $raw = new RawMessage($this->normalizeLineEndings($rfc822));
        $symfonyEnvelope = $this->toSymfonyEnvelope($envelope);
        $transport = $this->createTransport();
        $requireTls = $this->requiredTls();

        try {
            $sent = $transport->send($raw, $symfonyEnvelope);

            $providerMessageId = null;
            $providerMessageIdError = null;
            if (\is_object($sent)) {
                try {
                    $mid = $sent->getMessageId();
                    $providerMessageId = \is_string($mid) && $mid !== '' ? $mid : null;
                } catch (\Error $e) {
                    // Symfony may throw if SentMessage::$messageId was never initialized.
                    $providerMessageId = null;
                    $providerMessageIdError = $e->getMessage();
                }
            }

            $debug = [
                'transport' => 'smtp',
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'auto_tls' => $this->autoTls,
                'require_tls' => $requireTls,
            ];

            if ($providerMessageIdError !== null) {
                $debug['provider_message_id_error'] = $providerMessageIdError;
            }
            return DeliveryResult::sent($providerMessageId, $debug);
        } catch (TransportExceptionInterface $e) {
            $providerCode = $e->getCode() !== 0 ? (string) $e->getCode() : null;

            throw new TransportException(
                'SMTP delivery failed.',
                $providerCode,
                $this->trimProviderMessage($e->getMessage()),
                $e
            );
        }
    }

    /**
     * Builds the Symfony SMTP transport used by delivery and readiness verification.
     *
     * @return EsmtpTransport
     */
    private function createTransport(): EsmtpTransport
    {
        $tls = ($this->encryption === 'ssl');
        $transport = new EsmtpTransport($this->host, $this->port, $tls);
        $stream = $transport->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout($this->timeoutSeconds);
        }

        if ($this->credentials instanceof PasswordCredentials) {
            $transport->setUsername($this->credentials->username());
            $transport->setPassword($this->credentials->password());
        } elseif ($this->credentials instanceof XOAuth2Credentials) {
            $transport->setAuthenticators([new XOAuth2Authenticator()]);
            $transport->setUsername($this->credentials->username());
            $transport->setPassword($this->credentials->accessToken());
        }

        $transport->setAutoTls($this->autoTls);
        $transport->setRequireTls($this->requiredTls());

        return $transport;
    }


    /**
     * Disconnects the SMTP transport without changing the completed verification outcome.
     *
     * @param EsmtpTransport $transport SMTP transport to close.
     * @return void
     */
    private function disconnectTransport(EsmtpTransport $transport): void
    {
        try {
            $transport->stop();
        } catch (Throwable) {
            // Deliberately ignored; completed verification is authoritative.
        }
    }

    /**
     * Resolves whether TLS is mandatory for this transport.
     *
     * @return bool
     */
    private function requiredTls(): bool
    {
        if ($this->requireTls !== null) {
            return $this->requireTls;
        }

        return $this->port === 465 || $this->port === 587;
    }

    /**
     * Identifies Symfony's stable SMTP authentication-rejection outcome.
     *
     * @param TransportExceptionInterface $exception SMTP transport failure.
     * @return bool
     */
    private function isAuthenticationFailure(TransportExceptionInterface $exception): bool
    {
        return $this->credentials !== null && $exception->getCode() === 535;
    }

    /**
     * Converts the library envelope into Symfony's Envelope type.
     *
     * @param Envelope $envelope Library envelope.
     * @return SymfonyEnvelope
     */
    private function toSymfonyEnvelope(Envelope $envelope): SymfonyEnvelope
    {
        $from = new Address($envelope->from()->email());
        $to = [];

        foreach ($envelope->recipients() as $recipient) {
            $to[] = new Address($recipient->email());
        }

        return new SymfonyEnvelope($from, $to);
    }

    /**
     * Normalizes line endings to CRLF to preserve RFC822 transport expectations.
     *
     * @param string $rfc822 RFC822 input.
     * @return string
     */
    private function normalizeLineEndings(string $rfc822): string
    {
        $rfc822 = \str_replace("\r\n", "\n", $rfc822);
        $rfc822 = \str_replace("\r", "\n", $rfc822);

        return \str_replace("\n", "\r\n", $rfc822);
    }

    /**
     * Trims provider messages to a safe, concise string.
     *
     * @param string $message Provider error message.
     * @return string
     */
    private function trimProviderMessage(string $message): string
    {
        $message = \trim($message);

        if (\strlen($message) > 2000) {
            return \substr($message, 0, 2000);
        }

        return $message;
    }
}