<?php

namespace App\Support;

/**
 * Registry of the prerequisites Curator needs before it can do useful work.
 *
 * Single source of truth for the requirement keys the frontend switches on, and
 * for how hard each one is enforced. Adding a prerequisite should mean adding
 * one entry here.
 *
 * Tiers, strongest first:
 * - block: nothing in the app works. The user is held on /setup until it is fixed.
 * - lock:  one feature is dead. Its route still renders, with a lock panel in place
 *          of the working area.
 * - nudge: the feature works but produces worse output. Advisory only.
 *
 * Scope says who owns the prerequisite, which is what decides whether the user
 * in front of us can fix it at all.
 */
class SetupRequirements
{
    public const TIER_BLOCK = 'block';

    public const TIER_LOCK = 'lock';

    public const TIER_NUDGE = 'nudge';

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_USER = 'user';

    public const SCOPE_WORKSPACE = 'workspace';

    public const STATE_SATISFIED = 'satisfied';

    public const STATE_PARTIAL = 'partial';

    public const STATE_MISSING = 'missing';

    public const OAUTH_APP = 'oauth_app';

    public const SOCIAL_CREDENTIAL = 'social_credential';

    public const AI_PROVIDER = 'ai_provider';

    public const WORKSPACE = 'workspace';

    public const AI_IMAGE_PROVIDER = 'ai_image_provider';

    public const FEED = 'feed';

    public const SYNCED_POSTS = 'synced_posts';

    public const BRAND_KIT = 'brand_kit';

    public const ONBOARDING = 'onboarding';

    /**
     * Ordered strongest-tier-first, which is also the order the setup screen and
     * the readiness meter render them in.
     *
     * @return array<string, array{
     *     key: string,
     *     tier: string,
     *     scope: string,
     *     route: string,
     *     label: string,
     *     admin_only: bool,
     * }>
     */
    public static function all(): array
    {
        return [
            self::OAUTH_APP => [
                'key' => self::OAUTH_APP,
                'tier' => self::TIER_BLOCK,
                'scope' => self::SCOPE_PLATFORM,
                'route' => '/oauth-apps',
                'label' => 'Register a platform app',
                'admin_only' => true,
            ],
            self::SOCIAL_CREDENTIAL => [
                'key' => self::SOCIAL_CREDENTIAL,
                'tier' => self::TIER_LOCK,
                'scope' => self::SCOPE_USER,
                'route' => '/credentials',
                'label' => 'Connect a social account',
                'admin_only' => false,
            ],
            self::AI_PROVIDER => [
                'key' => self::AI_PROVIDER,
                'tier' => self::TIER_LOCK,
                'scope' => self::SCOPE_USER,
                'route' => '/settings/ai',
                'label' => 'Set up an AI provider',
                'admin_only' => false,
            ],
            self::WORKSPACE => [
                'key' => self::WORKSPACE,
                'tier' => self::TIER_LOCK,
                'scope' => self::SCOPE_USER,
                'route' => '/workspaces',
                'label' => 'Create a workspace',
                'admin_only' => false,
            ],
            self::AI_IMAGE_PROVIDER => [
                'key' => self::AI_IMAGE_PROVIDER,
                'tier' => self::TIER_NUDGE,
                'scope' => self::SCOPE_USER,
                'route' => '/settings/ai',
                'label' => 'Set up AI image generation',
                'admin_only' => false,
            ],
            self::FEED => [
                'key' => self::FEED,
                'tier' => self::TIER_NUDGE,
                'scope' => self::SCOPE_WORKSPACE,
                'route' => '/workspaces',
                'label' => 'Add a feed to a workspace',
                'admin_only' => false,
            ],
            self::SYNCED_POSTS => [
                'key' => self::SYNCED_POSTS,
                'tier' => self::TIER_NUDGE,
                'scope' => self::SCOPE_WORKSPACE,
                'route' => '/workspaces',
                'label' => 'Sync posts from your feeds',
                'admin_only' => false,
            ],
            self::BRAND_KIT => [
                'key' => self::BRAND_KIT,
                'tier' => self::TIER_NUDGE,
                'scope' => self::SCOPE_USER,
                'route' => '/brand-kits',
                'label' => 'Create a brand kit',
                'admin_only' => false,
            ],
            self::ONBOARDING => [
                'key' => self::ONBOARDING,
                'tier' => self::TIER_NUDGE,
                'scope' => self::SCOPE_USER,
                'route' => '/onboarding',
                'label' => 'Complete your profile',
                'admin_only' => false,
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{key: string, tier: string, scope: string, route: string, label: string, admin_only: bool}|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return self::find($key) !== null;
    }

    /** @return list<string> */
    public static function keysForTier(string $tier): array
    {
        return array_keys(array_filter(
            self::all(),
            fn (array $spec) => $spec['tier'] === $tier,
        ));
    }
}
