<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Authentication/ExternalUser.php');

class ExternalUserTest extends TestBase
{
    public function testConstructionWithRequiredFields(): void
    {
        $user = new ExternalUser('jdoe', 'jdoe@example.com', 'John', 'Doe');

        $this->assertSame('jdoe', $user->username);
        $this->assertSame('jdoe@example.com', $user->email);
        $this->assertSame('John', $user->firstName);
        $this->assertSame('Doe', $user->lastName);
        $this->assertNull($user->phone);
        $this->assertNull($user->organization);
        $this->assertNull($user->title);
    }

    public function testConstructionWithOptionalFields(): void
    {
        $user = new ExternalUser('jdoe', 'jdoe@example.com', 'John', 'Doe', '+1555', 'ACME', 'Engineer');

        $this->assertSame('+1555', $user->phone);
        $this->assertSame('ACME', $user->organization);
        $this->assertSame('Engineer', $user->title);
    }
}
