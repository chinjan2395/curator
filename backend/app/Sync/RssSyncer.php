<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class RssSyncer
{
    public function test(string $url): array|JsonResponse
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['message' => 'Enter a valid RSS URL.'], 422);
        }

        $resp = $this->fetch($url);
        if (! $resp->ok()) {
            return response()->json(['message' => 'Failed to load RSS feed.', 'status' => $resp->status()], 422);
        }

        $body = $resp->body();
        $items = $this->parseItems($body);
        if (empty($items)) {
            return response()->json(['message' => 'Could not parse RSS/Atom feed (no items found).'], 422);
        }

        return [
            'message' => 'RSS connection successful.',
            'feed_title' => $this->extractTitle($body),
            'item_count' => count($items),
            'sample_title' => $items[0]['title'] ?? null,
        ];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $url = trim((string) $feed->source_url);
        if ($url === '') {
            return response()->json(['message' => 'source_url is not set on this feed.'], 422);
        }

        $resp = $this->fetch($url);
        if (! $resp->ok()) {
            return response()->json(['message' => 'Failed to load RSS feed.', 'status' => $resp->status()], 422);
        }

        $items = $this->parseItems($resp->body());
        if (empty($items)) {
            return response()->json(['message' => 'Could not parse RSS/Atom feed (no items found).'], 422);
        }

        $created = 0;
        foreach (array_slice($items, 0, 25) as $item) {
            if (! $item['external_id']) {
                continue;
            }

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => $item['external_id']],
                [
                    'title' => $item['title'] ?? null,
                    'content' => $item['content'] ?? '',
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'video_url' => $item['url'] ?? null,
                    'posted_at' => $item['posted_at'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'RSS sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function fetch(string $url)
    {
        return Http::timeout(15)
            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
            ->get($url);
    }

    private function parseItems(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $root) {
            return [];
        }

        $items = [];

        if (isset($root->channel->item)) {
            foreach ($root->channel->item as $item) {
                $items[] = $this->mapRssItem($item);
            }

            return array_values(array_filter($items, fn ($i) => ! empty($i['external_id'])));
        }

        if (isset($root->entry)) {
            foreach ($root->entry as $entry) {
                $items[] = $this->mapAtomEntry($entry);
            }
        }

        return array_values(array_filter($items, fn ($i) => ! empty($i['external_id'])));
    }

    private function extractTitle(string $xml): ?string
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $root) {
            return null;
        }

        if (isset($root->channel->title)) {
            $t = trim((string) $root->channel->title);

            return $t !== '' ? $t : null;
        }

        if (isset($root->title)) {
            $t = trim((string) $root->title);

            return $t !== '' ? $t : null;
        }

        return null;
    }

    private function mapRssItem(\SimpleXMLElement $item): array
    {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? ''));
        $desc = (string) ($item->description ?? '');
        $contentEncoded = '';

        $ns = $item->getNamespaces(true);
        if (isset($ns['content'])) {
            $contentEncoded = (string) ($item->children($ns['content'])->encoded ?? '');
        }

        $body = trim(strip_tags($contentEncoded !== '' ? $contentEncoded : $desc));
        $pubDate = trim((string) ($item->pubDate ?? ''));
        $postedAt = $pubDate !== '' ? date(DATE_ATOM, strtotime($pubDate)) : null;

        $thumb = null;
        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            $thumb = (string) ($media->thumbnail['url'] ?? '') ?: (string) ($media->content['url'] ?? '');
        }
        if (! $thumb && isset($item->enclosure)) {
            $type = (string) ($item->enclosure['type'] ?? '');
            if (str_starts_with($type, 'image/')) {
                $thumb = (string) ($item->enclosure['url'] ?? '');
            }
        }

        $externalId = $guid !== '' ? $guid : ($link !== '' ? $link : ($title !== '' ? sha1($title.$body) : null));

        return [
            'external_id' => $externalId,
            'title' => $title !== '' ? $title : null,
            'content' => $body,
            'url' => $link !== '' ? $link : null,
            'thumbnail_url' => $thumb ?: null,
            'posted_at' => $postedAt,
        ];
    }

    private function mapAtomEntry(\SimpleXMLElement $entry): array
    {
        $title = trim((string) ($entry->title ?? ''));
        $id = trim((string) ($entry->id ?? ''));
        $updated = trim((string) ($entry->updated ?? ''));
        $published = trim((string) ($entry->published ?? ''));

        $link = null;
        if (isset($entry->link)) {
            foreach ($entry->link as $l) {
                $rel = (string) ($l['rel'] ?? '');
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) ($l['href'] ?? '') ?: $link;
                }
            }
        }

        $content = (string) ($entry->content ?? '');
        $summary = (string) ($entry->summary ?? '');
        $body = trim(strip_tags($content !== '' ? $content : $summary));

        $postedAt = $published !== '' ? $published : ($updated !== '' ? $updated : null);
        $externalId = $id !== '' ? $id : ($link ?: ($title !== '' ? sha1($title.$body) : null));

        return [
            'external_id' => $externalId,
            'title' => $title !== '' ? $title : null,
            'content' => $body,
            'url' => $link,
            'thumbnail_url' => null,
            'posted_at' => $postedAt,
        ];
    }
}
