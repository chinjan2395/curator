<?php

namespace App\DTOs;

class PostUpdateData
{
    public function __construct(
        public readonly ?string $status,
        public readonly ?bool $pinned,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? null,
            pinned: array_key_exists('pinned', $data) && $data['pinned'] !== null
                ? (bool) $data['pinned']
                : null,
        );
    }
}
