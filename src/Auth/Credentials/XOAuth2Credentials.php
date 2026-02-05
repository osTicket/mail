<?php

declare(strict_types=1);

namespace Osticket\Mail\Auth\Credentials;

/**
 * XOAUTH2 credentials for transport authentication.
 *
 * Holds only currently-valid auth material.
 * Token lifecycle and refresh are owned by the app.
 */
final class XOAuth2Credentials implements AuthCredentials
{
    /**
     * Creates XOAUTH2 authentication credentials.
     *
     * @param string $username Authentication username.
     * @param string $accessToken OAuth2 access token.
     * @param string|null $provider Optional provider identifier.
     * @return void
     */
    public function __construct(
        private string $username,
        private string $accessToken,
        private ?string $provider = null
    ) {}

    /**
     * Returns the authentication username.
     *
     * @return string
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Returns the OAuth2 access token.
     *
     * @return string
     */
    public function accessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Returns the optional provider identifier.
     *
     * @return string|null
     */
    public function provider(): ?string
    {
        return $this->provider;
    }
}
