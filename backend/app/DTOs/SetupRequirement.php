<?php

namespace App\DTOs;

use App\Support\SetupRequirements;

/**
 * One evaluated prerequisite: what it is, whether it is met, and — crucially —
 * whether the user asking can do anything about it.
 *
 * `fixableByMe` is decided server-side so the client never has to re-derive it
 * from a role and get it wrong.
 */
class SetupRequirement
{
    public function __construct(
        public readonly string $key,
        public readonly string $state,
        public readonly string $tier,
        public readonly string $scope,
        public readonly bool $fixableByMe,
        public readonly string $route,
        public readonly string $label,
        public readonly string $detail,
        public readonly array $meta = [],
    ) {}

    /**
     * Build from the registry spec, so tier/scope/route/label can never drift
     * away from SetupRequirements.
     */
    public static function fromSpec(
        array $spec,
        string $state,
        bool $fixableByMe,
        string $detail,
        array $meta = [],
    ): self {
        return new self(
            key: $spec['key'],
            state: $state,
            tier: $spec['tier'],
            scope: $spec['scope'],
            fixableByMe: $fixableByMe,
            route: $spec['route'],
            label: $spec['label'],
            detail: $detail,
            meta: $meta,
        );
    }

    public function isSatisfied(): bool
    {
        return $this->state === SetupRequirements::STATE_SATISFIED;
    }

    public function isBlocking(): bool
    {
        return $this->tier === SetupRequirements::TIER_BLOCK && ! $this->isSatisfied();
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'state' => $this->state,
            'tier' => $this->tier,
            'scope' => $this->scope,
            'fixable_by_me' => $this->fixableByMe,
            'route' => $this->route,
            'label' => $this->label,
            'detail' => $this->detail,
            'meta' => $this->meta,
        ];
    }
}
