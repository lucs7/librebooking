<?php

require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'plugins/ExternalLogin/OAuth2/namespace.php');

class OAuth2 implements IExternalLoginProvider
{
    private \GuzzleHttp\Client $httpClient;
    private OAuth2Options $options;

    public function __construct(?\GuzzleHttp\Client $httpClient = null, ?OAuth2Options $options = null)
    {
        $this->httpClient = $httpClient ?? new \GuzzleHttp\Client();
        $this->options = $options ?? new OAuth2Options();
    }

    public function getProviderName(): string
    {
        return 'oauth2';
    }

    public function getButtonLabel(): string
    {
        return $this->options->getButtonLabel();
    }

    public function getAuthorizeUrl(): string
    {
        $endpoint = $this->options->getAuthorizeUrl();
        if ($this->options->getStripTrailingSlash()) {
            $endpoint = rtrim($endpoint, '/');
        }

        return $endpoint . '?' . http_build_query([
            'client_id'     => $this->options->getClientId(),
            'redirect_uri'  => $this->buildRedirectUri(),
            'scope'         => 'openid email profile',
            'response_type' => 'code',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function handleCallback(): ExternalUser
    {
        $code = $_GET['code'] ?? null;
        if (!$code) {
            throw new \RuntimeException('Missing authorization code.');
        }

        $response = $this->httpClient->post($this->options->getTokenUrl(), ['form_params' => [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->buildRedirectUri(),
            'client_id'     => $this->options->getClientId(),
            'client_secret' => $this->options->getClientSecret(),
        ]]);

        $tokenData = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $accessToken = $tokenData['access_token'] ?? null;
        if (!$accessToken) {
            throw new \RuntimeException('OAuth2: access_token missing.');
        }

        $uResp = $this->httpClient->get($this->options->getUserInfoUrl(), [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);
        $user = json_decode((string) $uResp->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $email = $user['email'] ?? '';
        if ($email === '') {
            throw new \RuntimeException('Email is not set in your OAuth2 profile.');
        }

        return new ExternalUser(
            username: $user['preferred_username'] ?? $email,
            email: $email,
            firstName: $user['given_name'] ?? '',
            lastName: $user['family_name'] ?? '',
            phone: $user['phone_number'] ?? null,
            organization: $user['organization'] ?? null,
            title: $user['title'] ?? null,
        );
    }

    private function buildRedirectUri(): string
    {
        $scriptUrl = rtrim(Configuration::Instance()->GetScriptUrl(), '/');
        $path = '/' . ltrim($this->options->getRedirectUri(), '/');
        if (str_ends_with($scriptUrl, '/Web') && str_starts_with($path, '/Web/')) {
            $path = substr($path, 4);
        }
        return $scriptUrl . $path;
    }
}
