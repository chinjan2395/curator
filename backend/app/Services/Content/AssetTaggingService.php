<?php

namespace App\Services\Content;

use App\Models\Asset;
use App\Services\AI\AiProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class AssetTaggingService
{
    public function __construct(
        private readonly AiProviderInterface $ai,
    ) {}

    /** @return list<string> */
    public function suggestTags(Asset $asset): array
    {
        $fallback = $this->fallbackTags($asset);

        try {
            $raw = $this->ai->generateText($this->buildPrompt($asset), [
                'platform' => 'content-library',
                'campaign_name' => 'asset-tagging',
            ]);

            $parsed = $this->parseTags($raw);

            return $parsed !== [] ? $parsed : $fallback;
        } catch (RuntimeException) {
            return $fallback;
        }
    }

    private function buildPrompt(Asset $asset): string
    {
        $name = $asset->file_name ?? 'upload';
        $type = $asset->type ?? 'file';
        $mime = $asset->mime_type ?? 'unknown';

        return <<<PROMPT
Analyze this content library upload and return ONLY a JSON array of 3 to 8 short tags (lowercase words or hyphenated phrases) for search and organization. No markdown, no explanation.

File name: {$name}
Asset type: {$type}
MIME type: {$mime}
PROMPT;
    }

    /** @return list<string> */
    private function parseTags(string $raw): array
    {
        $candidate = trim($raw);
        $decoded = json_decode($candidate, true);

        if (! is_array($decoded) && preg_match('/^\s*(\[[\s\S]*\])\s*$/', $candidate, $matches)) {
            $decoded = json_decode($matches[1], true);
        }

        if (! is_array($decoded) && preg_match('/\[[\s\S]*?\]/', $candidate, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $tags = [];
        foreach ($decoded as $tag) {
            if (! is_string($tag)) {
                continue;
            }
            $slug = Str::slug(trim($tag));
            if ($slug !== '') {
                $tags[] = $slug;
            }
        }

        return array_values(array_unique($tags));
    }

    /** @return list<string> */
    private function fallbackTags(Asset $asset): array
    {
        $base = Str::slug(pathinfo((string) $asset->file_name, PATHINFO_FILENAME));
        $tags = array_filter([$base, $asset->type]);

        return array_values(array_unique($tags));
    }
}
