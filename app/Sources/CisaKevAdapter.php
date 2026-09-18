<?php

namespace App\Sources;

use App\Models\Source;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * CISA Known Exploited Vulnerabilities. Config: days? (14) — only entries added within this window.
 */
class CisaKevAdapter implements SourceAdapter
{
    use FetchesHttp;

    public const URL = 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json';

    public function fetch(Source $source): iterable
    {
        $response = $this->http()->acceptJson()->get(self::URL);

        if (! $response->successful()) {
            throw new RuntimeException("CISA KEV returned HTTP {$response->status()}");
        }

        $since = now()->subDays((int) $source->setting('days', 14))->startOfDay();

        foreach ($response->json('vulnerabilities', []) as $vuln) {
            $added = Carbon::parse($vuln['dateAdded']);

            if ($added->lessThan($since)) {
                continue;
            }

            yield new ItemData(
                url: "https://nvd.nist.gov/vuln/detail/{$vuln['cveID']}",
                title: "Actively exploited: {$vuln['cveID']} {$vuln['vendorProject']} {$vuln['product']} — {$vuln['vulnerabilityName']}",
                kind: 'vulnerability',
                excerpt: trim($vuln['shortDescription'].' Required action: '.$vuln['requiredAction']),
                publisher: 'CISA KEV',
                publishedAt: $added,
                language: 'en',
                category: 'vulnerability',
                severity: 3,
                entities: ['cves' => [$vuln['cveID']], 'orgs' => [$vuln['vendorProject']]],
            );
        }
    }
}
