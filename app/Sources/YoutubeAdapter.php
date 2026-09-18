<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * YouTube Data API search. Config: queries (list), relevance_language?, region_code?
 * The default quota allows 100 searches a day: keep queries × runs per day under that.
 */
class YoutubeAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $key = config('services.youtube.key');

        if (! $key) {
            throw new RuntimeException('YOUTUBE_API_KEY is not set');
        }

        $since = $source->last_success_at ?? now()->subDay();

        foreach ((array) $source->setting('queries', []) as $query) {
            $response = $this->http()->acceptJson()->get('https://www.googleapis.com/youtube/v3/search', array_filter([
                'part' => 'snippet',
                'type' => 'video',
                'order' => 'date',
                'maxResults' => 25,
                'q' => $query,
                'publishedAfter' => $since->toIso8601ZuluString(),
                'relevanceLanguage' => $source->setting('relevance_language'),
                'regionCode' => $source->setting('region_code'),
                'key' => $key,
            ]));

            if (! $response->successful()) {
                throw new RuntimeException("YouTube returned HTTP {$response->status()}");
            }

            foreach ($response->json('items', []) as $video) {
                $snippet = $video['snippet'];

                yield new ItemData(
                    url: 'https://www.youtube.com/watch?v='.$video['id']['videoId'],
                    title: html_entity_decode($snippet['title'], ENT_QUOTES | ENT_HTML5),
                    kind: 'social',
                    excerpt: $this->plain($snippet['description'] ?? null),
                    publisher: 'YouTube: '.$snippet['channelTitle'],
                    publishedAt: Carbon::parse($snippet['publishedAt']),
                    author: $snippet['channelTitle'],
                );
            }
        }
    }
}
