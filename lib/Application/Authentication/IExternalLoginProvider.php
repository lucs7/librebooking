<?php

declare(strict_types=1);

interface IExternalLoginProvider
{
    public function getProviderName(): string;

    public function getButtonLabel(): string;

    public function getAuthorizeUrl(): string;

    /**
     * Reads callback parameters directly from $_GET or $_SESSION depending on the provider.
     * Throws \RuntimeException on any failure (missing code, HTTP error, missing email, etc.).
     */
    public function handleCallback(): ExternalUser;
}
