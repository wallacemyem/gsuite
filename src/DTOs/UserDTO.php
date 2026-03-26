<?php

namespace BrickServers\GoogleWorkspace\DTOs;

readonly class UserDTO
{
    public function __construct(
        public string $email,
        public string $givenName,
        public string $familyName,
        public ?string $password = null,
        public bool $changePasswordAtNextLogin = true,
        public bool $suspended = false,
        public ?string $phone = null,
        public ?string $title = null,
        public ?array $customSchemas = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? [];

        return new self(
            email: $data['primaryEmail'] ?? '',
            givenName: $name['givenName'] ?? '',
            familyName: $name['familyName'] ?? '',
            password: null,
            changePasswordAtNextLogin: $data['changePasswordAtNextLogin'] ?? false,
            suspended: $data['suspended'] ?? false,
            phone: $data['phones'][0]['value'] ?? null,
            title: $data['organizations'][0]['title'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'primaryEmail' => $this->email,
            'name' => [
                'givenName' => $this->givenName,
                'familyName' => $this->familyName,
            ],
            'password' => $this->password,
            'changePasswordAtNextLogin' => $this->changePasswordAtNextLogin,
            'suspended' => $this->suspended,
            'customSchemas' => $this->customSchemas,
        ];
    }
}
