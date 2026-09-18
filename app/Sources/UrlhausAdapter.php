<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Malware URLs from abuse.ch URLhaus hosted on Cambodian domains. Config: host_suffixes? (['.kh'])
 */
class UrlhausAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $key = config('services.abusech.key');

        if (! $key) {
            throw new RuntimeException('ABUSECH_AUTH_KEY is not set');
        }

        $response = $this->http()->acceptJson()->withHeaders(['Auth-Key' => $key])
            ->get('https://urlhaus-api.abuse.ch/v1/urls/recent/limit/1000/');

        if (! $response->successful()) {
            throw new RuntimeException("URLhaus returned HTTP {$response->status()}");
        }

        $suffixes = $source->setting('host_suffixes', ['.kh']);

        foreach ($response->json('urls', []) as $entry) {
            $host = mb_strtolower((string) ($entry['host'] ?? ''));

            if (! collect($suffixes)->contains(fn ($suffix) => str_ends_with($host, $suffix))) {
                continue;
            }

            yield new ItemData(
                // Link to the URLhaus report, never to the malware itself.
                url: $entry['urlhaus_reference'],
                title: "Malware URL hosted on {$host} ({$entry['threat']})",
                kind: 'threat',
                excerpt: 'Defanged URL: '.str_replace(['http', '.'], ['hxxp', '[.]'], (string) $entry['url'])
                    .'. Status: '.($entry['url_status'] ?? 'unknown')
                    .'. Tags: '.implode(', ', $entry['tags'] ?? []),
                publisher: 'abuse.ch URLhaus',
                publishedAt: isset($entry['date_added']) ? Carbon::parse($entry['date_added']) : null,
                language: 'en',
                countryCode: str_ends_with($host, '.kh') ? 'KH' : null,
                category: 'attack',
                severity: 4,
                isCambodia: str_ends_with($host, '.kh'),
                entities: ['domains' => [$host]],
            );
        }
    }
}
