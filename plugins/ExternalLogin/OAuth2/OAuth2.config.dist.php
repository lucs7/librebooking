<?php

// plugins/ExternalLogin/OAuth2/OAuth2.config.dist.php
// Copy this file to OAuth2.config.php and fill in your provider's values.

return [
    'settings' => [
        'oauth2' => [
            // Display name shown on the login button
            'name' => 'OAuth2',

            // If true, trailing slash is stripped from the authorize URL (true/false)
            'strip.trailing.slash' => true,

            // Authorization endpoint URL of your OAuth2 provider
            'url.authorize' => '',

            // Token endpoint URL of your OAuth2 provider
            'url.token' => '',

            // Userinfo endpoint URL of your OAuth2 provider
            'url.userinfo' => '',

            // OAuth2 client credentials
            'client.id' => '',
            'client.secret' => '',

            // Redirect URI registered in your OAuth2 provider application
            // Must match the value configured in the provider's allowed redirect URIs
            'redirect.uri' => '/Web/oauth2-auth.php',
        ],
    ],
];
