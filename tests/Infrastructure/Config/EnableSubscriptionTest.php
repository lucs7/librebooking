<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Config/namespace.php');

class EnableSubscriptionTest extends TestBase
{
    public function setup(): void
    {
        parent::setup();

        Configuration::SetInstance(null);
    }

    public function testGeneratesKeyWhenIcsEnabledAndKeyEmptyInCurrentNestedFormat(): void
    {
        $path = $this->writeTempConfig(
            <<<'PHP'
<?php
return [
    'settings' => [
        'ics' => [
            'enabled' => true,
            'subscription.key' => '',
        ],
    ],
];
PHP
        );

        try {
            $config = $this->loadConfigurationFile($path);
            $config->EnableSubscription($path);

            $reloaded = $this->loadConfigurationFile($path);
            $newKey = $reloaded->GetKey(ConfigKeys::ICS_SUBSCRIPTION_KEY);

            $this->assertNotEmpty($newKey, 'Subscription key should have been generated');
            $this->assertStringNotContainsString(
                "'subscription.key' => '',",
                file_get_contents($path),
                'Placeholder empty key should have been replaced'
            );
        } finally {
            unlink($path);
        }
    }

    public function testDoesNothingWhenIcsIsDisabled(): void
    {
        $path = $this->writeTempConfig(
            <<<'PHP'
<?php
return [
    'settings' => [
        'ics' => [
            'enabled' => false,
            'subscription.key' => '',
        ],
    ],
];
PHP
        );

        try {
            $before = file_get_contents($path);

            $config = $this->loadConfigurationFile($path);
            $config->EnableSubscription($path);

            $this->assertSame($before, file_get_contents($path), 'Config file should not change when ICS is disabled');
        } finally {
            unlink($path);
        }
    }

    public function testDoesNothingWhenKeyIsAlreadySet(): void
    {
        $path = $this->writeTempConfig(
            <<<'PHP'
<?php
return [
    'settings' => [
        'ics' => [
            'enabled' => true,
            'subscription.key' => 'already-set-key',
        ],
    ],
];
PHP
        );

        try {
            $before = file_get_contents($path);

            $config = $this->loadConfigurationFile($path);
            $config->EnableSubscription($path);

            $this->assertSame($before, file_get_contents($path), 'Config file should not change when a key is already set');
        } finally {
            unlink($path);
        }
    }

    private function writeTempConfig(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'enable-subscription-test-');
        if ($path === false) {
            throw new RuntimeException('Failed to create temporary config file.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Failed to write temporary config file.');
        }

        return $path;
    }

    private function loadConfigurationFile(string $path): ConfigurationFile
    {
        $conf = [];
        $loaded = require $path;

        if (is_array($loaded) && isset($loaded['settings'])) {
            return new ConfigurationFile($loaded);
        }

        if (isset($conf['settings'])) {
            return new ConfigurationFile([Configuration::SETTINGS => $conf['settings']]);
        }

        throw new RuntimeException("Invalid config file: 'settings' section missing");
    }
}
