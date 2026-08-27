<?php

namespace App\Services;

use App\DTOs\SetupRequirement;
use App\Models\User;
use App\Repositories\SetupReadinessRepository;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\AI\Text\TextKeyResolver;
use App\Support\AiImageProviders;
use App\Support\AiTextProviders;
use App\Support\OAuthProviderAliases;
use App\Support\SetupRequirements;
use Illuminate\Support\Facades\Cache;

/**
 * Evaluates every prerequisite in SetupRequirements for one user and reports
 * what is missing, how badly, and whether that user can fix it themselves.
 *
 * This is the single contract behind the setup gate, the per-feature lock
 * panels and the readiness meter — so they can never disagree with each other.
 */
class SetupReadinessService
{
    public function __construct(
        private readonly SetupReadinessRepository $repository,
        private readonly TextKeyResolver $textKeys,
        private readonly ImageKeyResolver $imageKeys,
        private readonly NotificationService $notifications,
    ) {}

    public function forUser(User $user): array
    {
        $workspaceIds = $this->repository->workspaceIds($user->id);
        $feedIds = $this->repository->feedIds($workspaceIds);
        $hasSyncedPosts = $this->repository->hasSyncedPosts($feedIds);

        $requirements = [
            $this->oauthApp($user),
            $this->socialCredential($user),
            $this->aiProvider($user),
            $this->workspace($workspaceIds),
            $this->aiImageProvider($user),
            $this->feed($feedIds),
            $this->syncedPosts($hasSyncedPosts),
            $this->brandKit($user),
            $this->onboarding($user),
        ];

        $blocking = [];
        $ready = true;
        $payload = [];

        foreach ($requirements as $requirement) {
            $payload[$requirement->key] = $requirement->toArray();

            if ($requirement->isBlocking()) {
                $blocking[] = $requirement->key;
            }

            if (! $requirement->isSatisfied()) {
                $ready = false;
            }
        }

        return array_merge(
            [
                'ready' => $ready,
                'blocking' => $blocking,
                'requirements' => $payload,
            ],
            // Legacy flat booleans. GettingStartedChecklist.vue still reads these;
            // keep them until that component is retired.
            [
                'is_onboarded' => (bool) $user->is_onboarded,
                'has_social_credentials' => $this->repository->hasAnyCredential($user->id),
                'has_workspaces' => $workspaceIds !== [],
                'has_feeds' => $feedIds !== [],
                'has_synced_posts' => $hasSyncedPosts,
                'has_campaigns' => $this->repository->hasCampaigns($user->id),
                'has_approved_packages' => $this->repository->hasApprovedPackages($user->id),
                'has_scheduled_posts' => $this->repository->hasScheduledPosts($user->id),
            ],
        );
    }

    /**
     * Tell every admin that someone is stuck behind a platform-scope blocker.
     *
     * Throttled to once per requester per day: a whole team hitting the gate on
     * the same morning must not turn into an inbox flood.
     *
     * @return array{notified: int, throttled: bool}
     */
    public function notifyAdmins(User $requester): array
    {
        $lock = "setup:notify-admin:{$requester->id}";

        if (! Cache::add($lock, true, now()->addDay())) {
            return ['notified' => 0, 'throttled' => true];
        }

        $admins = $this->repository->admins();
        $name = $requester->name ?: $requester->email;
        $notified = 0;

        foreach ($admins as $admin) {
            if ((int) $admin->id === (int) $requester->id) {
                continue;
            }

            $notified++;

            $this->notifications->notify(
                $admin,
                'setup_required',
                'Someone is waiting on Curator setup',
                "{$name} cannot use Curator until a social platform app is registered in OAuth Apps.",
                ['requested_by' => $requester->id, 'route' => '/oauth-apps'],
            );
        }

        return ['notified' => $notified, 'throttled' => false];
    }

    /**
     * The one blocking prerequisite: without an OAuth app on some provider, no
     * account can ever be connected and every connect endpoint answers 503.
     */
    private function oauthApp(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::OAUTH_APP);
        $connectable = OAuthProviderAliases::connectableSocialProviders($user->id);
        $satisfied = $connectable !== [];

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $satisfied ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: $user->isAdmin(),
            detail: $satisfied
                ? 'You can connect: '.$this->humanList($connectable).'.'
                : 'No provider has a client ID and secret saved, so no account can be connected yet.',
            meta: ['connectable_providers' => array_values($connectable)],
        );
    }

    private function socialCredential(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::SOCIAL_CREDENTIAL);

        if ($this->repository->hasConnectedCredential($user->id)) {
            return SetupRequirement::fromSpec(
                spec: $spec,
                state: SetupRequirements::STATE_SATISFIED,
                fixableByMe: true,
                detail: 'At least one account is connected and healthy.',
            );
        }

        // A credential row that exists but is expired or needs re-auth is a
        // different problem from never having connected — say which.
        if ($this->repository->hasAnyCredential($user->id)) {
            return SetupRequirement::fromSpec(
                spec: $spec,
                state: SetupRequirements::STATE_PARTIAL,
                fixableByMe: true,
                detail: 'Your connected accounts have all expired or need reconnecting.',
            );
        }

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: 'No social account is connected yet.',
        );
    }

    /**
     * Satisfied when any real text provider can run — a BYOK key, a platform env
     * key, or a configured local Ollama. Deliberately not "the user saved a key":
     * an install running on GROQ_API_KEY from .env works fine with an empty AI
     * Settings page.
     */
    private function aiProvider(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::AI_PROVIDER);

        $available = [];
        foreach (AiTextProviders::selectableIds() as $provider) {
            if ($provider === AiTextProviders::OLLAMA) {
                if ($this->hasOllamaUrl()) {
                    $available[] = $provider;
                }

                continue;
            }

            if ($this->textKeys->isAvailable($provider, $user)) {
                $available[] = $provider;
            }
        }

        if ($available === []) {
            return SetupRequirement::fromSpec(
                spec: $spec,
                state: SetupRequirements::STATE_MISSING,
                fixableByMe: true,
                detail: 'No AI provider has a usable key, so captions and campaigns cannot generate.',
                meta: ['available_providers' => [], 'source' => null],
            );
        }

        $primary = $available[0];
        $source = $this->textKeySource($primary, $user);

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: SetupRequirements::STATE_SATISFIED,
            fixableByMe: true,
            detail: $this->keySourceDetail(AiTextProviders::label($primary), $source),
            meta: ['available_providers' => $available, 'source' => $source],
        );
    }

    private function workspace(array $workspaceIds): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::WORKSPACE);
        $satisfied = $workspaceIds !== [];

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $satisfied ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: $satisfied
                ? 'You own '.count($workspaceIds).' '.(count($workspaceIds) === 1 ? 'workspace' : 'workspaces').'.'
                : 'Workspaces hold your feeds and the embed widget.',
        );
    }

    private function aiImageProvider(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::AI_IMAGE_PROVIDER);

        $available = array_values(array_filter(
            AiImageProviders::selectableIds(),
            fn (string $provider) => $this->imageKeys->isAvailable($provider, $user),
        ));

        if ($available === []) {
            return SetupRequirement::fromSpec(
                spec: $spec,
                state: SetupRequirements::STATE_MISSING,
                fixableByMe: true,
                detail: 'Image generation is unavailable. Captions still work without it.',
                meta: ['available_providers' => [], 'source' => null],
            );
        }

        $primary = $available[0];
        $source = $this->imageKeySource($primary, $user);

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: SetupRequirements::STATE_SATISFIED,
            fixableByMe: true,
            detail: $this->keySourceDetail(AiImageProviders::label($primary), $source),
            meta: ['available_providers' => $available, 'source' => $source],
        );
    }

    private function feed(array $feedIds): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::FEED);
        $satisfied = $feedIds !== [];

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $satisfied ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: $satisfied
                ? count($feedIds).' '.(count($feedIds) === 1 ? 'feed is' : 'feeds are').' set up.'
                : 'Feeds are what the curator and the embed widget pull posts from.',
        );
    }

    private function syncedPosts(bool $hasSyncedPosts): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::SYNCED_POSTS);

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $hasSyncedPosts ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: $hasSyncedPosts
                ? 'Posts have synced from your feeds.'
                : 'Nothing has synced yet, so there is nothing to curate or analyse.',
        );
    }

    private function brandKit(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::BRAND_KIT);
        $satisfied = $this->repository->hasMasterBrandKit($user->id);

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $satisfied ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: $satisfied
                ? 'AI content uses your brand colours, fonts and voice.'
                : 'Without one, AI content uses generic defaults.',
        );
    }

    private function onboarding(User $user): SetupRequirement
    {
        $spec = SetupRequirements::find(SetupRequirements::ONBOARDING);

        return SetupRequirement::fromSpec(
            spec: $spec,
            state: $user->is_onboarded ? SetupRequirements::STATE_SATISFIED : SetupRequirements::STATE_MISSING,
            fixableByMe: true,
            detail: $user->is_onboarded
                ? 'Your profile is complete.'
                : 'Tell us about your brand so AI content sounds like you.',
        );
    }

    private function hasOllamaUrl(): bool
    {
        $url = config('services.ai.ollama.url');

        return is_string($url) && trim($url) !== '';
    }

    private function textKeySource(string $provider, User $user): ?string
    {
        if ($provider === AiTextProviders::OLLAMA) {
            return 'local';
        }

        if ($this->textKeys->hasUserKey($provider, $user)) {
            return 'byok';
        }

        return AiTextProviders::platformConfigured($provider) ? 'platform' : null;
    }

    private function imageKeySource(string $provider, User $user): ?string
    {
        if ($this->imageKeys->hasUserKey($provider, $user)) {
            return 'byok';
        }

        return AiImageProviders::platformConfigured($provider) ? 'platform' : null;
    }

    private function keySourceDetail(string $label, ?string $source): string
    {
        return match ($source) {
            'byok' => "Using your own {$label} key.",
            'platform' => "Using the platform {$label} key — add your own any time.",
            'local' => "Using {$label}.",
            default => "{$label} is available.",
        };
    }

    /** @param list<string> $items */
    private function humanList(array $items): string
    {
        $labels = array_map(fn (string $item) => ucfirst($item), array_values($items));

        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.$last;
    }
}
