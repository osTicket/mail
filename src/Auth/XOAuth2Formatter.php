<?php

declare(strict_types=1);

namespace Osticket\Mail\Auth;

use Osticket\Mail\Auth\Credentials\XOAuth2Credentials;

/**
 * Formats XOAUTH2 SASL initial client response (usable by SMTP AUTH and IMAP AUTHENTICATE).
 */
final class XOAuth2Formatter
{
    /**
     * Builds a base64-encoded XOAUTH2 initial client response.
     *
     * @param XOAuth2Credentials $creds XOAUTH2 credentials.
     * @return string
     */
    public function format(XOAuth2Credentials $creds): string
    {
        // RFC 7628 / Gmail-style XOAUTH2 format:
        // "user=<email>\x01auth=Bearer <token>\x01\x01"
        $payload =
            'user=' . $creds->username() . "\x01" .
            'auth=Bearer ' . $creds->accessToken() . "\x01\x01";

        return \base64_encode($payload);
    }
}
