<?php

declare(strict_types=1);

namespace Osticket\Mail\Transport;

use Osticket\Mail\Support\{DeliveryResult, Envelope};
use Osticket\Mail\Transport\Contracts\TransportContract;
use Osticket\Mail\Transport\Exceptions\TransportException;
use Symfony\Component\Mailer\Envelope as SymfonyEnvelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport as SymfonySendmailTransport;
use Symfony\Component\Mime\{Address, RawMessage};

final class SendmailTransport implements TransportContract
{
    private string $command;

    /**
     * Sends RFC822 messages using sendmail by delegating process semantics to Symfony Mailer.
     *
     * @param string $command Sendmail command (e.g. "/usr/sbin/sendmail -bs").
     * @return void
     */
    public function __construct(string $command = '/usr/sbin/sendmail -bs')
    {
        $this->command = $command;
    }

    /**
     * Delivers a pre-rendered RFC822 message using an explicit envelope sender/recipients.
     *
     * @param Envelope $envelope Envelope sender/recipients for delivery.
     * @param string $rfc822 Fully rendered RFC822 message string (authoritative; no re-rendering here).
     * @return DeliveryResult
     *
     * @throws TransportException When sendmail invocation or delivery fails.
     */
    public function send(Envelope $envelope, string $rfc822): DeliveryResult
    {
        if (\trim($rfc822) === '') {
            throw new TransportException('Empty RFC822 payload.');
        }

        $raw = new RawMessage($this->normalizeLineEndings($rfc822));
        $symfonyEnvelope = $this->toSymfonyEnvelope($envelope);

        $transport = new SymfonySendmailTransport($this->command);

        try {
            $sent = $transport->send($raw, $symfonyEnvelope);

            $providerMessageId = null;
            if (\is_object($sent)) {
                $mid = $sent->getMessageId();
                $providerMessageId = \is_string($mid) && $mid !== '' ? $mid : null;
            }

            return DeliveryResult::sent($providerMessageId, [
                'transport' => 'sendmail',
                'command' => $this->command,
            ]);
        } catch (TransportExceptionInterface $e) {
            $providerCode = $e->getCode() !== 0 ? (string) $e->getCode() : null;

            throw new TransportException(
                'Sendmail delivery failed.',
                $providerCode,
                $this->trimProviderMessage($e->getMessage()),
                $e
            );
        }
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
