<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Dmarc;

use Osticket\Mail\Message\Message;
use Osticket\Mail\Support\Envelope;

/**
 * Validates a message and envelope against DMARC-related rules.
 */
interface DmarcValidatorInterface
{
    /**
     * Validates DMARC-related constraints for the outbound message.
     *
     * @param Message $message The outbound message.
     * @param Envelope $envelope The delivery envelope.
     * @param array<string, mixed> $context Optional validation context.
     * @return DmarcValidationResult
     */
    public function validate(Message $message, Envelope $envelope, array $context = []): DmarcValidationResult;
}
