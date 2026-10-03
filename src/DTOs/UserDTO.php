<?php

namespace BrickServers\GoogleWorkspace\DTOs;

/**
 * Optional fields default to null, which means "not set": they are left out of
 * API requests, so an update only changes the fields you actually provide.
 */
readonly class UserDTO
{
    public function __construct(
        public string $email,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $password = null,
        public ?bool $changePasswordAtNextLogin = null,
        public ?bool $suspended = null,
        public ?string $phone = null,
        public ?string $title = null,
        public ?array $customSchemas = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $name = (array)($data['name'] ?? []);

        return new self(
            email: $data['primaryEmail'] ?? '',
            givenName: $name['givenName'] ?? null,
            familyName: $name['familyName'] ?? null,
            password: null,
            changePasswordAtNextLogin: $data['changePasswordAtNextLogin'] ?? null,
            suspended: $data['suspended'] ?? null,
            phone: ((array)($data['phones'][0] ?? []))['value'] ?? null,
            title: ((array)($data['organizations'][0] ?? []))['title'] ?? null,
            customSchemas: isset($data['customSchemas']) ? (array)$data['customSchemas'] : null,
        );
    }

    public function toArray(): array
    {
        $name = array_filter(
            ['givenName' => $this->givenName, 'familyName' => $this->familyName],
            fn ($part) => $part !== null && $part !== '',
        );

        $data = [
            'primaryEmail' => $this->email !== '' ? $this->email : null,
            'name' => $name ?: null,
            'password' => $this->password,
            'changePasswordAtNextLogin' => $this->changePasswordAtNextLogin,
            'suspended' => $this->suspended,
            'phones' => $this->phone !== null ? [['value' => $this->phone, 'type' => 'work', 'primary' => true]] : null,
            'organizations' => $this->title !== null ? [['title' => $this->title, 'primary' => true]] : null,
            'customSchemas' => $this->customSchemas,
        ];

        return array_filter($data, fn ($value) => $value !== null);
    }
}
