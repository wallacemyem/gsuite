<?php

namespace BrickServers\GoogleWorkspace\DTOs;

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
            'email' => $this->email,
            'name' => $this->name,
            'description' => $this->description,
        ]);
    }
}
