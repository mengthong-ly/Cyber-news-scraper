<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * AlienVault OTX pulses matching a keyword. Config: query? ('cambodia')
 */
class OtxAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $key = config('services.otx.key');

        if (! $key) {
            throw new RuntimeException('OTX_API_KEY is not set');
        }

        $response = $this->http()->acceptJson()->withHeaders(['X-OTX-API-KEY' => $key])
            ->get('https://otx.alienvault.com/api/v1/search/pulses', [
                'q' => $source->setting('query', 'cambodia'),
                'sort' => '-modified',
                'limit' => 50,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("OTX returned HTTP {$response->status()}");
        }

        foreach ($response->json('results', []) as $pulse) {
            $targets = array_map('strval', $pulse['targeted_countries'] ?? []);

            yield new ItemData(
                url: "https://otx.alienvault.com/pulse/{$pulse['id']}",
                title: 'OTX pulse: '.$pulse['name'],
                kind: 'threat',
                excerpt: $this->plain($pulse['description'] ?? null),
                publisher: 'AlienVault OTX',
                publishedAt: Carbon::parse($pulse['modified'] ?? $pulse['created']),
                language: 'en',
                category: 'attack',
                severity: 3,
                isCambodia: in_array('Cambodia', $targets, true),
                entities: array_filter(['threat_actors' => array_filter([$pulse['adversary'] ?? null])]),
            );
        }
    }
}
