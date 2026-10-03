<?php

namespace BrickServers\GoogleWorkspace\DTOs;

/**
 * Null fields are "not set" and are left out of API requests.
 */
readonly class GroupDTO
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? '',
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email !== '' ? $this->email : null,
            'name' => $this->name,
            'description' => $this->description,
        ], fn ($value) => $value !== null);
    }
}
