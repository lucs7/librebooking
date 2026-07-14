<?php

/**
 * Registry of configuration keys that have been deprecated and removed.
 *
 * When a config key is removed from ConfigKeys, add it here so that
 * existing config files using the old key produce a helpful deprecation
 * message instead of an "Unknown config key" warning.
 */
class DeprecatedConfigKeys
{
    /**
     * Maps removed canonical config keys to the reason they were removed.
     *
     * Format: 'key' => 'reason for removal'
     *
     * Keys MUST be lowercase — findReason() normalizes lookups via
     * strtolower(), so mixed-case entries here will never match.
     *
     * @var array<string, string>
     */
    public const DEPRECATED_KEYS = [
        'security.x-xss' => 'X-XSS-Protection header is obsolete and has been removed from all modern browsers',

        // Keycloak authentication — removed in 5.0; use the OAuth2 ExternalLogin plugin instead
        'authentication.keycloak.login.enabled'      => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.keycloak.url'                => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.keycloak.realm'              => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.keycloak.client.id'          => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.keycloak.client.secret'      => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.keycloak.client.uri'         => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',

        // OAuth2 core config — removed in 5.0; moved to plugins/ExternalLogin/OAuth2/OAuth2.config.php
        'authentication.oauth2.login.enabled'        => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.name'                 => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.strip.trailing.slash' => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.url.authorize'        => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.url.token'            => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.url.userinfo'         => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.client.id'            => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.client.secret'        => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.oauth2.client.uri'           => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
    ];

    /**
     * Maps removed legacy config keys to the reason they were removed.
     *
     * These are the legacy (pre-rewrite) forms of removed keys
     * (e.g. 'security.security.x-xss' for canonical 'security.x-xss').
     * Separated so that when legacy key support is removed entirely,
     * this constant can simply be deleted.
     *
     * Keys MUST be lowercase.
     *
     * @var array<string, string>
     */
    public const DEPRECATED_LEGACY_KEYS = [
        'security.security.x-xss' => 'X-XSS-Protection header is obsolete and has been removed from all modern browsers',
        'authentication.allow.keycloak.login' => 'Keycloak is now handled by the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
        'authentication.allow.oauth2.login'   => 'OAuth2 config has moved to the OAuth2 ExternalLogin plugin. Set plugins.external.login.providers = OAuth2 and configure OAuth2.config.php.',
    ];

    /**
     * Checks if a key has been deprecated and removed.
     * Returns the removal reason if found, or null if the key is not
     * a known deprecated key.
     */
    public static function findReason(string $key): ?string
    {
        $normalizedKey = strtolower($key);

        return self::DEPRECATED_KEYS[$normalizedKey]
            ?? self::DEPRECATED_LEGACY_KEYS[$normalizedKey]
            ?? null;
    }
}
