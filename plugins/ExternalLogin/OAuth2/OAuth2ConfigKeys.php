<?php

require_once(ROOT_DIR . 'lib/Config/PluginConfigKeys.php');

class OAuth2ConfigKeys extends PluginConfigKeys
{
    public const CONFIG_ID = 'OAuth2';

    public const BUTTON_LABEL = [
        'key' => 'name',
        'type' => 'string',
        'default' => 'OAuth2',
        'label' => 'Button Label',
        'description' => 'Display name shown on the login button',
        'section' => 'oauth2',
    ];

    public const STRIP_TRAILING_SLASH = [
        'key' => 'strip.trailing.slash',
        'type' => 'boolean',
        'default' => true,
        'label' => 'Strip Trailing Slash',
        'description' => 'Remove trailing slash from the authorize URL before appending query string',
        'section' => 'oauth2',
    ];

    public const URL_AUTHORIZE = [
        'key' => 'url.authorize',
        'type' => 'string',
        'default' => '',
        'label' => 'Authorization URL',
        'description' => 'OAuth2 authorization endpoint URL',
        'section' => 'oauth2',
    ];

    public const URL_TOKEN = [
        'key' => 'url.token',
        'type' => 'string',
        'default' => '',
        'label' => 'Token URL',
        'description' => 'OAuth2 token endpoint URL',
        'section' => 'oauth2',
    ];

    public const URL_USERINFO = [
        'key' => 'url.userinfo',
        'type' => 'string',
        'default' => '',
        'label' => 'Userinfo URL',
        'description' => 'OAuth2 userinfo endpoint URL',
        'section' => 'oauth2',
    ];

    public const CLIENT_ID = [
        'key' => 'client.id',
        'type' => 'string',
        'default' => '',
        'label' => 'Client ID',
        'description' => 'OAuth2 client ID',
        'section' => 'oauth2',
    ];

    public const CLIENT_SECRET = [
        'key' => 'client.secret',
        'type' => 'string',
        'default' => '',
        'label' => 'Client Secret',
        'description' => 'OAuth2 client secret',
        'section' => 'oauth2',
        'is_private' => true,
    ];

    public const REDIRECT_URI = [
        'key' => 'redirect.uri',
        'type' => 'string',
        'default' => '/Web/oauth2-auth.php',
        'label' => 'Redirect URI',
        'description' => 'Callback URI registered in your OAuth2 provider',
        'section' => 'oauth2',
    ];
}
