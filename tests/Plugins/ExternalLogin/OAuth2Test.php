<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'plugins/ExternalLogin/OAuth2/namespace.php');
require_once(ROOT_DIR . 'plugins/ExternalLogin/OAuth2/OAuth2.php');

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class OAuth2Test extends TestBase
{
    public function tearDown(): void
    {
        parent::tearDown();
        unset($_GET['code']);
    }

    private function makeOptions(array $overrides = []): OAuth2Options
    {
        $opts = $this->createMock(OAuth2Options::class);
        $opts->method('getButtonLabel')->willReturn($overrides['buttonLabel'] ?? 'My SSO');
        $opts->method('getStripTrailingSlash')->willReturn($overrides['stripSlash'] ?? true);
        $opts->method('getAuthorizeUrl')->willReturn($overrides['authorizeUrl'] ?? 'https://provider.example/auth');
        $opts->method('getTokenUrl')->willReturn($overrides['tokenUrl'] ?? 'https://provider.example/token');
        $opts->method('getUserInfoUrl')->willReturn($overrides['userInfoUrl'] ?? 'https://provider.example/userinfo');
        $opts->method('getClientId')->willReturn($overrides['clientId'] ?? 'test-client-id');
        $opts->method('getClientSecret')->willReturn($overrides['clientSecret'] ?? 'test-client-secret');
        $opts->method('getRedirectUri')->willReturn($overrides['redirectUri'] ?? '/Web/oauth2-auth.php');
        return $opts;
    }

    private function makeClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    public function testGetProviderName(): void
    {
        $plugin = new OAuth2($this->makeClient([]), $this->makeOptions());
        $this->assertSame('oauth2', $plugin->getProviderName());
    }

    public function testGetButtonLabel(): void
    {
        $plugin = new OAuth2($this->makeClient([]), $this->makeOptions(['buttonLabel' => 'Corporate SSO']));
        $this->assertSame('Corporate SSO', $plugin->getButtonLabel());
    }

    public function testGetAuthorizeUrlContainsRequiredParams(): void
    {
        $plugin = new OAuth2($this->makeClient([]), $this->makeOptions([
            'authorizeUrl' => 'https://provider.example/auth',
            'clientId' => 'my-client',
        ]));

        $url = $plugin->getAuthorizeUrl();

        $this->assertStringContainsString('https://provider.example/auth', $url);
        $this->assertStringContainsString('client_id=my-client', $url);
        $this->assertStringContainsString('scope=openid%20email%20profile', $url);
        $this->assertStringContainsString('response_type=code', $url);
    }

    public function testGetAuthorizeUrlStripsTrailingSlash(): void
    {
        $plugin = new OAuth2($this->makeClient([]), $this->makeOptions([
            'authorizeUrl' => 'https://provider.example/auth/',
            'stripSlash' => true,
        ]));

        $this->assertStringNotContainsString('auth/?', $plugin->getAuthorizeUrl());
    }

    public function testHandleCallbackReturnsExternalUser(): void
    {
        $_GET['code'] = 'auth-code-123';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['access_token' => 'tok-abc'])),
            new Response(200, [], json_encode([
                'email' => 'user@example.com',
                'preferred_username' => 'jdoe',
                'given_name' => 'Jane',
                'family_name' => 'Doe',
                'phone_number' => '+1555',
                'organization' => 'ACME',
                'title' => 'Dev',
            ])),
        ]);

        $plugin = new OAuth2($client, $this->makeOptions());
        $user = $plugin->handleCallback();

        $this->assertSame('jdoe', $user->username);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('Jane', $user->firstName);
        $this->assertSame('Doe', $user->lastName);
        $this->assertSame('+1555', $user->phone);
        $this->assertSame('ACME', $user->organization);
        $this->assertSame('Dev', $user->title);

        unset($_GET['code']);
    }

    public function testHandleCallbackFallsBackUsernameToEmail(): void
    {
        $_GET['code'] = 'auth-code-456';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['access_token' => 'tok-xyz'])),
            new Response(200, [], json_encode([
                'email' => 'user@example.com',
                'given_name' => 'Jane',
                'family_name' => 'Doe',
            ])),
        ]);

        $plugin = new OAuth2($client, $this->makeOptions());
        $user = $plugin->handleCallback();

        $this->assertSame('user@example.com', $user->username);

        unset($_GET['code']);
    }

    public function testHandleCallbackThrowsWhenCodeMissing(): void
    {
        unset($_GET['code']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/authorization code/i');

        $plugin = new OAuth2($this->makeClient([]), $this->makeOptions());
        $plugin->handleCallback();
    }

    public function testHandleCallbackThrowsWhenAccessTokenMissing(): void
    {
        $_GET['code'] = 'code-789';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['error' => 'invalid_grant'])),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/access_token missing/i');

        $plugin = new OAuth2($client, $this->makeOptions());
        $plugin->handleCallback();
    }

    public function testHandleCallbackThrowsWhenEmailMissing(): void
    {
        $_GET['code'] = 'code-000';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['access_token' => 'tok'])),
            new Response(200, [], json_encode(['given_name' => 'No', 'family_name' => 'Email'])),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/email/i');

        $plugin = new OAuth2($client, $this->makeOptions());
        $plugin->handleCallback();
    }
}
