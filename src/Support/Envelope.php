<?php

declare(strict_types=1);

namespace Osticket\Mail\Support;

use InvalidArgumentException;
use Osticket\Mail\Message\Message;

final class Envelope
{
    private EmailAddress $from;
    private AddressList $recipients;

    /**
     * Represents the delivery envelope (MAIL FROM / RCPT TO) independently of message headers.
     *
     * @param EmailAddress $from Envelope sender address.
     * @param AddressList $recipients Envelope recipient addresses.
     * @return void
     */
    public function __construct(EmailAddress $from, AddressList $recipients)
    {
        $this->from = $from;
        $this->recipients = $recipients;
    }

    /**
     * Creates a default delivery envelope from message headers.
     *
     * This convenience helper uses the message From address as MAIL FROM and
     * combines To, CC, and BCC recipients as RCPT TO addresses. Applications
     * with bounce-address or policy requirements should construct Envelope
     * explicitly instead.
     *
     * @param Message $message Prepared or preparable message.
     * @return self
     *
     * @throws InvalidArgumentException When From or recipients are missing.
     */
    public static function fromMessage(Message $message): self
    {
        $from = $message->from();

        if ($from === null) {
            throw new InvalidArgumentException('Cannot create envelope from message without a From address.');
        }

        return new self($from, $message->allRecipients());
    }

    /**
     * Returns the envelope sender (MAIL FROM).
     *
     * @return EmailAddress
     */
    public function from(): EmailAddress
    {
        return $this->from;
    }

    /**
     * Returns the envelope recipients (RCPT TO list).
     *
     * @return AddressList
     */
    public function recipients(): AddressList
    {
        return $this->recipients;
    }

    /**
     * Returns a small, stable diagnostic snapshot of the envelope.
     *
     * @return array{from: string, recipients: string[], recipient_count: int}
     */
    public function summary(): array
    {
        $recipients = [];

        foreach ($this->recipients as $recipient) {
            $recipients[] = $recipient->email();
        }

        return [
            'from' => $this->from->email(),
            'recipients' => $recipients,
            'recipient_count' => \count($recipients),
        ];
    }
}