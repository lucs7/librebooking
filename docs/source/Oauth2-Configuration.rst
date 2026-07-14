OAuth2 Configuration
====================

The OAuth2 ExternalLogin plugin lets you log in via any OAuth2/OIDC provider
such as `authentik <https://goauthentik.io>`__, `Keycloak <https://www.keycloak.org/>`__,
or any other provider that supports the Authorization Code flow.

.. note::

   Upgrading from an earlier version? See :ref:`oauth2-migration` below.

IdP Configuration
-----------------

Create a confidential client (Client ID + Client Secret) in your identity
provider. Set the allowed redirect URI to::

   <LibreBooking URL>/Web/oauth2-auth.php

e.g. ``https://librebooking.example.com/Web/oauth2-auth.php``

Required scopes: ``openid``, ``email``, ``profile``.

The plugin reads the following claims from the userinfo endpoint:

- ``email`` → email
- ``given_name`` → first name
- ``family_name`` → last name
- ``preferred_username`` → username
- ``phone_number`` → phone
- ``organization`` → organization
- ``title`` → title

Plugin Setup
------------

1. Copy the plugin config template::

      cp plugins/ExternalLogin/OAuth2/OAuth2.config.dist.php \
         plugins/ExternalLogin/OAuth2/OAuth2.config.php

2. Edit ``OAuth2.config.php`` and fill in your provider's values:

   .. code-block:: php

      return [
          'settings' => [
              'oauth2' => [
                  'name'                => 'Sign in with Authentik',
                  'strip.trailing.slash' => false,
                  'url.authorize'       => 'https://authentik.io/application/o/authorize/',
                  'url.token'           => 'https://authentik.io/application/o/token/',
                  'url.userinfo'        => 'https://authentik.io/application/o/userinfo/',
                  'client.id'           => 'your-client-id',
                  'client.secret'       => 'your-client-secret',
                  'redirect.uri'        => '/Web/oauth2-auth.php',
              ],
          ],
      ];

3. Enable the plugin in ``config/config.php`` (plugins section)::

      'external.login.providers' => 'OAuth2',

The login page will now show an **Sign in with Authentik** button alongside
the standard username/password form.

Trailing Slash Handling
^^^^^^^^^^^^^^^^^^^^^^^

By default the plugin strips the trailing slash from ``url.authorize`` before
appending the query string. Set ``strip.trailing.slash`` to ``false`` to
preserve the trailing slash as configured.

This setting affects only the authorize URL; token and userinfo URLs are
passed through unchanged.

.. _oauth2-migration:

Migrating from the Built-in OAuth2 Config
------------------------------------------

Before LibreBooking 5.0, OAuth2 settings lived in the ``authentication``
section of ``config.php``. They have moved to the plugin config file.

1. Follow the plugin setup steps above, copying your existing values across.

2. Enable the plugin::

      'external.login.providers' => 'OAuth2',

3. Remove the old ``authentication.oauth2.*`` keys from ``config.php``.
   Any key still present will produce a deprecation message in the PHP
   error log pointing to this guide.

Keycloak users
^^^^^^^^^^^^^^

The dedicated Keycloak integration was removed in 5.0. Keycloak supports
standard OIDC, so it works with the OAuth2 plugin. Use your Keycloak realm's
``.well-known/openid-configuration`` discovery document to find the three
endpoint URLs, then follow the plugin setup above.

Remove the old ``authentication.keycloak.*`` keys from ``config.php`` once
the plugin is configured.
