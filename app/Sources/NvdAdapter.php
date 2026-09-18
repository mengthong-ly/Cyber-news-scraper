<?php

namespace App\Sources;

use App\Models\Source;
use App\Models\WatchlistTerm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * New CVEs from NVD that mention a watchlist product. Only matching CVEs are stored.
 */
class NvdAdapter implements SourceAdapter
{
    use FetchesHttp;

    private const PAGE_SIZE = 2000;

    public function fetch(Source $source): iterable
    {
        $products = WatchlistTerm::where('kind', 'product')->pluck('term')->map(fn ($t) => mb_strtolower($t))->all();
        $end = now()->utc();
        $start = Carbon::parse($source->state['published_until'] ?? $end->copy()->subDay())->utc();
        $key = config('services.nvd.key');
        $index = 0;

        do {
            $request = $this->http()->acceptJson();

            if ($key) {
                $request = $request->withHeaders(['apiKey' => $key]);
            }

            $response = $request->get('https://services.nvd.nist.gov/rest/json/cves/2.0', [
                'pubStartDate' => $start->format('Y-m-d\TH:i:s.v'),
                'pubEndDate' => $end->format('Y-m-d\TH:i:s.v'),
                'resultsPerPage' => self::PAGE_SIZE,
                'startIndex' => $index,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("NVD returned HTTP {$response->status()}");
            }

            foreach ($response->json('vulnerabilities', []) as $entry) {
                $cve = $entry['cve'];
                $description = collect($cve['descriptions'] ?? [])->firstWhere('lang', 'en')['value'] ?? '';
                $haystack = mb_strtolower($description);

                if (! collect($products)->contains(fn ($p) => str_contains($haystack, $p))) {
                    continue;
                }

                yield new ItemData(
                    url: "https://nvd.nist.gov/vuln/detail/{$cve['id']}",
                    title: "{$cve['id']}: ".mb_substr($description, 0, 200),
                    kind: 'vulnerability',
                    excerpt: mb_substr($description, 0, 2000),
                    publisher: 'NVD',
                    publishedAt: Carbon::parse($cve['published']),
                    language: 'en',
                    category: 'vulnerability',
                    severity: self::severity($cve),
                    entities: ['cves' => [$cve['id']]],
                );
            }

            $index += self::PAGE_SIZE;
            $total = (int) $response->json('totalResults', 0);

            if ($index < $total) {
                // Without an API key NVD allows only a few requests per 30 seconds.
                Sleep::for($key ? 1 : 7)->seconds();
            }
        } while ($index < $total);

        $source->state = array_merge($source->state ?? [], ['published_until' => $end->toIso8601String()]);
    }

    /**
     * @param  array<string, mixed>  $cve
     */
    private static function severity(array $cve): int
    {
        $score = data_get($cve, 'metrics.cvssMetricV31.0.cvssData.baseScore')
            ?? data_get($cve, 'metrics.cvssMetricV40.0.cvssData.baseScore')
            ?? 0;

        return match (true) {
            $score >= 9 => 4,
            $score >= 7 => 3,
            default => 2,
        };
    }
}
