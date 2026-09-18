<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Ransomware leak-site victims for a country. Config: country? (KH), recent_days? (7)
 * The key-less v2 API is for personal use only; configure the PRO base URL and key in services.ransomware_live.
 */
class RansomwareLiveAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $country = strtoupper((string) $source->setting('country', 'KH'));
        $config = config('services.ransomware_live');
        $request = $this->http()->acceptJson();

        if (filled($config['key'])) {
            $request = $request->withHeaders([$config['key_header'] => $config['key']]);
        }

        $response = $request->get(rtrim($config['base_url'], '/')."/countryvictims/{$country}");

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("ransomware.live returned HTTP {$response->status()}");
        }

        $recentSince = now()->subDays((int) $source->setting('recent_days', 7));

        foreach ($response->json() as $victim) {
            $name = trim((string) ($victim['post_title'] ?? $victim['victim'] ?? ''));
            $group = trim((string) ($victim['group_name'] ?? $victim['group'] ?? 'unknown'));

            if ($name === '') {
                continue;
            }

            $discovered = isset($victim['discovered']) ? Carbon::parse($victim['discovered']) : null;

            yield new ItemData(
                url: 'https://www.ransomware.live/group/'.rawurlencode($group).'?victim='.rawurlencode($name),
                title: "Ransomware claim: {$name} ({$group})",
                kind: 'threat',
                excerpt: $this->plain($victim['description'] ?? null),
                publisher: 'ransomware.live',
                publishedAt: $discovered,
                language: 'en',
                countryCode: $country,
                category: 'attack',
                // Old claims are context, not alerts.
                severity: $discovered !== null && $discovered->greaterThanOrEqualTo($recentSince) ? 5 : 3,
                isCambodia: $country === 'KH',
                entities: ['orgs' => [$name], 'threat_actors' => [$group]],
            );
        }
    }
}
