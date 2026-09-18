<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Bluesky post search with the ministry's own account (app password). Config: queries (list), language?
 * Anonymous search is refused, so BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD are required.
 */
class BlueskyAdapter implements SourceAdapter
{
    use FetchesHttp;

    private const HOST = 'https://bsky.social/xrpc';

    public function fetch(Source $source): iterable
    {
        $credentials = config('services.bluesky');

        if (! $credentials['identifier'] || ! $credentials['app_password']) {
            throw new RuntimeException('BLUESKY_IDENTIFIER and BLUESKY_APP_PASSWORD are not set');
        }

        $session = $this->http()->post(self::HOST.'/com.atproto.server.createSession', [
            'identifier' => $credentials['identifier'],
            'password' => $credentials['app_password'],
        ]);

        if (! $session->successful()) {
            throw new RuntimeException("Bluesky login failed with HTTP {$session->status()}");
        }

        $since = $source->last_success_at ?? now()->subDay();

        foreach ((array) $source->setting('queries', []) as $query) {
            $response = $this->http()->withToken($session->json('accessJwt'))
                ->get(self::HOST.'/app.bsky.feed.searchPosts', array_filter([
                    'q' => $query,
                    'sort' => 'latest',
                    'limit' => 50,
                    'since' => $since->toIso8601ZuluString(),
                    'lang' => $source->setting('language'),
                ]));

            if (! $response->successful()) {
                throw new RuntimeException("Bluesky search returned HTTP {$response->status()}");
            }

            foreach ($response->json('posts', []) as $post) {
                $text = (string) data_get($post, 'record.text', '');
                $handle = (string) data_get($post, 'author.handle');
                $rkey = basename((string) $post['uri']);

                if ($text === '') {
                    continue;
                }

                yield new ItemData(
                    url: "https://bsky.app/profile/{$handle}/post/{$rkey}",
                    title: mb_substr($text, 0, 200),
                    kind: 'social',
                    excerpt: mb_substr($text, 0, 2000),
                    publisher: 'Bluesky',
                    publishedAt: Carbon::parse(data_get($post, 'record.createdAt', $post['indexedAt'] ?? 'now')),
                    language: data_get($post, 'record.langs.0'),
                    author: $handle,
                );
            }
        }
    }
}
