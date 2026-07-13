<?php

declare(strict_types=1);

readonly class ExternalUser
{
    public function __construct(
        public string $username,
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phone = null,
        public ?string $organization = null,
        public ?string $title = null,
    ) {
    }
}
