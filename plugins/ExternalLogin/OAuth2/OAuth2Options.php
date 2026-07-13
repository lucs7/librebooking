<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');

class OAuth2Options
{
    public function __construct()
    {
        Configuration::Instance()->Register(
            dirname(__FILE__) . '/OAuth2.config.php',
            dirname(__FILE__) . '/.env',
            OAuth2ConfigKeys::CONFIG_ID,
            false,
            OAuth2ConfigKeys::class
        );
    }

    private function GetConfig(array $configDef, $converter = null): mixed
    {
        return Configuration::Instance()->File(OAuth2ConfigKeys::CONFIG_ID)->GetKey($configDef, $converter);
    }

    public function getButtonLabel(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::BUTTON_LABEL);
    }

    public function getStripTrailingSlash(): bool
    {
        return $this->GetConfig(OAuth2ConfigKeys::STRIP_TRAILING_SLASH, new BooleanConverter());
    }

    public function getAuthorizeUrl(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::URL_AUTHORIZE);
    }

    public function getTokenUrl(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::URL_TOKEN);
    }

    public function getUserInfoUrl(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::URL_USERINFO);
    }

    public function getClientId(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::CLIENT_ID);
    }

    public function getClientSecret(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::CLIENT_SECRET);
    }

    public function getRedirectUri(): string
    {
        return $this->GetConfig(OAuth2ConfigKeys::REDIRECT_URI);
    }
}
