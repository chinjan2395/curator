<?php

namespace App\Console\Commands;

use App\Models\TrendSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeTrendsCommand extends Command
{
    protected $signature = 'ai:scrape-trends';

    protected $description = 'Scrape free trend sources for the self-learning engine';

    public function handle(): int
    {
        $response = Http::timeout(20)->get(
            'https://trends.google.com/trends/trendingsearches/daily/rss?geo=US',
        );

        if ($response->successful()) {
            $xml = @simplexml_load_string($response->body());
            if ($xml) {
                foreach ($xml->channel->item ?? [] as $item) {
                    TrendSnapshot::create([
                        'source' => 'google_trends',
                        'title' => (string) ($item->title ?? 'Trend'),
                        'summary' => (string) ($item->description ?? ''),
                        'payload' => ['link' => (string) ($item->link ?? '')],
                        'captured_at' => now(),
                    ]);
                }
            }
        }

        $this->info('Trend scrape completed.');

        return self::SUCCESS;
    }
}
