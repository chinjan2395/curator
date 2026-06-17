<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostDuplicateGroup;
use App\Models\Workspace;

class DuplicateDetectionService
{
    public function detectForWorkspace(Workspace $workspace): void
    {
        PostDuplicateGroup::where('workspace_id', $workspace->id)
            ->where('status', 'open')
            ->each(function ($group) { $group->delete(); });

        $posts = Post::whereHas('feed', fn($q) => $q->where('workspace_id', $workspace->id))
            ->whereIn('status', ['pending', 'approved'])
            ->get(['id', 'post_url', 'video_url', 'content']);

        if ($posts->count() < 2) return;

        $dismissedSets = PostDuplicateGroup::where('workspace_id', $workspace->id)
            ->where('status', 'dismissed')
            ->with('posts:id')
            ->get()
            ->map(fn($g) => collect($g->posts->pluck('id'))->sort()->values()->toArray());

        $candidates = [];

        $byUrl = $posts->filter(fn($p) => !empty($p->post_url))
            ->groupBy(fn($p) => $this->normalizeUrl($p->post_url));
        foreach ($byUrl as $url => $group) {
            if ($group->count() > 1) {
                $candidates[] = ['type' => 'url', 'ids' => $group->pluck('id')->toArray()];
            }
        }

        $byVideo = $posts->filter(fn($p) => !empty($p->video_url))
            ->groupBy(fn($p) => $this->normalizeUrl($p->video_url));
        foreach ($byVideo as $url => $group) {
            if ($group->count() < 2) continue;
            $ids = $group->pluck('id')->toArray();
            $merged = false;
            foreach ($candidates as &$c) {
                if (!empty(array_intersect($c['ids'], $ids))) {
                    $c['ids'] = array_values(array_unique(array_merge($c['ids'], $ids)));
                    $merged = true;
                    break;
                }
            }
            unset($c);
            if (!$merged) {
                $candidates[] = ['type' => 'video_url', 'ids' => $ids];
            }
        }

        $urlGroupedIds = collect($candidates)->flatMap(fn($c) => $c['ids'])->unique()->toArray();
        $ungrouped = $posts->whereNotIn('id', $urlGroupedIds)->values();

        $textGroups = [];
        $n = $ungrouped->count();
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $p1 = $ungrouped[$i];
                $p2 = $ungrouped[$j];
                if (strlen((string) $p1->content) < 20 || strlen((string) $p2->content) < 20) continue;
                similar_text((string) $p1->content, (string) $p2->content, $pct);
                if ($pct >= 80.0) {
                    $found = false;
                    foreach ($textGroups as &$tg) {
                        if (in_array($p1->id, $tg['ids']) || in_array($p2->id, $tg['ids'])) {
                            $tg['ids'] = array_values(array_unique(array_merge($tg['ids'], [$p1->id, $p2->id])));
                            $found = true;
                            break;
                        }
                    }
                    unset($tg);
                    if (!$found) {
                        $textGroups[] = ['type' => 'text', 'ids' => [$p1->id, $p2->id]];
                    }
                }
            }
        }
        $candidates = array_merge($candidates, $textGroups);

        foreach ($candidates as $candidate) {
            $ids = collect($candidate['ids'])->unique()->sort()->values()->toArray();
            if (count($ids) < 2) continue;

            if ($dismissedSets->contains($ids)) continue;

            $group = PostDuplicateGroup::create([
                'workspace_id' => $workspace->id,
                'status' => 'open',
                'match_type' => $candidate['type'],
            ]);
            $group->posts()->attach($ids);
        }
    }

    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url(trim($url));
        if (!$parsed) return strtolower($url);

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host   = strtolower($parsed['host'] ?? '');
        $path   = rtrim($parsed['path'] ?? '', '/');

        parse_str($parsed['query'] ?? '', $params);
        $trackingKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_eid', 'ref'];
        foreach ($trackingKeys as $key) {
            unset($params[$key]);
        }
        ksort($params);
        $query = http_build_query($params);

        return $scheme . '://' . $host . $path . ($query ? '?' . $query : '');
    }
}
