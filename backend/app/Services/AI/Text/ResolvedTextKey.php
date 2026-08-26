<?php

namespace App\Services\AI\Text;

/**
 * The API key a text/caption generation will run with, and where it came from.
 *
 * `source` is recorded on the LearningSignal so BYOK and platform usage can be
 * told apart later without storing the key itself anywhere.
 */
final class ResolvedTextKey
{
    public const SOURCE_BYOK = 'byok';

    public const SOURCE_PLATFORM = 'platform';

    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly string $provider,
        public readonly ?string $apiKey,
        public readonly string $source,
    ) {}

    public static function byok(string $provider, string $apiKey): self
    {
        return new self($provider, $apiKey, self::SOURCE_BYOK);
    }

    public static function platform(string $provider, string $apiKey): self
    {
        return new self($provider, $apiKey, self::SOURCE_PLATFORM);
    }

    /** The stub provider (and Ollama, which has no BYOK concept) needs no credentials. */
    public static function none(string $provider): self
    {
        return new self($provider, null, self::SOURCE_NONE);
    }

    public function isByok(): bool
    {
        return $this->source === self::SOURCE_BYOK;
    }
}
