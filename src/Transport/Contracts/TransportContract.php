<?php

declare(strict_types=1);

namespace Osticket\Mail\Transport\Contracts;

use Osticket\Mail\Support\DeliveryResult;
use Osticket\Mail\Support\Envelope;

interface TransportContract
{
    /**
     * Delivers a pre-rendered RFC822 message using an explicit delivery envelope.
     *
     * @param Envelope $envelope Envelope sender/recipients for delivery.
     * @param string $rfc822 Fully rendered RFC822 message string.
     * @return DeliveryResult
     */
    public function send(Envelope $envelope, string $rfc822): DeliveryResult;
}
