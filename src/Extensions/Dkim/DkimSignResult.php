<?php

declare(strict_types=1);

namespace Osticket\Mail\Extensions\Dkim;

use Osticket\Mail\Message\Message;

final class DkimSignResult
{
    private bool $signed;
    private Message $message;

    /**
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * Creates a DKIM signing result.
     *
     * @param bool $signed Whether a DKIM signature was applied.
     * @param Message $message The (possibly mutated) message after signing.
     * @param array<string, mixed> $meta Optional diagnostics.
     * @return void
     */
    public function __construct(bool $signed, Message $message, array $meta = [])
    {
        $this->signed = $signed;
        $this->message = $message;
        $this->meta = $meta;
    }

    /**
     * Indicates whether a DKIM signature was applied.
     *
     * @return bool
     */
    public function signed(): bool
    {
        return $this->signed;
    }

    /**
     * Returns the message after DKIM signing.
     *
     * @return Message
     */
    public function message(): Message
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }
}
