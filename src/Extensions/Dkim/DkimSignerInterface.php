<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Dkim;

use Osticket\Mail\Message\Message;

/**
 * Signs a message using DKIM and returns a mutated message.
 */
interface DkimSignerInterface
{
    /**
     * Signs the message and returns a signed message instance.
     *
     * @param Message $message The message to be signed.
     * @param array<string, mixed> $context Optional signing context.
     * @return DkimSignResult
     */
    public function sign(Message $message, array $context = []): DkimSignResult;
}
