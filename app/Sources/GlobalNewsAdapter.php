<?php

namespace App\Sources;

use App\Models\Source;
use App\Support\Countries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Cyber news for every country from GDELT and Google News. Config: countries? (ISO codes), days? (1)
 * GDELT allows one request every 5 seconds, so a full run takes about 20 minutes.
 */
class GlobalNewsAdapter implements SourceAdapter
{
    use FetchesHttp;

    public function fetch(Source $source): iterable
    {
        $codes = $source->setting('countries') ?: config('cyber.countries');
        $days = max(1, (int) $source->setting('days', 1));
        $google = app(GoogleNewsAdapter::class);

        foreach ($codes as $i => $code) {
            $name = Countries::name($code);

            yield from $this->fromGdelt($code, $name, $days);

            $query = new Source(['type' => 'google_news', 'config' => [
                'query' => sprintf('(%s) "%s"', $this->orKeywords(), $name),
                'days' => $days,
                'country_code' => $code,
            ]]);

            try {
                yield from $google->fetch($query);
            } catch (\Throwable $e) {
                Log::warning("Global news: Google News skipped {$code}", ['error' => $e->getMessage()]);
            }

            if ($i < count($codes) - 1) {
                Sleep::for(config('cyber.gdelt_delay'))->seconds();
            }
        }
    }

    /**
     * @return list<ItemData>
     */
    private function fromGdelt(string $code, string $name, int $days): array
    {
        $params = [
            'query' => sprintf('(%s) sourcecountry:%s', $this->orKeywords(), preg_replace('/[^a-z]/', '', mb_strtolower($name))),
            'mode' => 'artlist', 'format' => 'json', 'maxrecords' => 100, 'timespan' => "{$days}d", 'sort' => 'datedesc',
        ];

        $body = $this->gdelt($params);

        // GDELT answers with plain text when rate limited: wait and retry once.
        if ($body !== null && ! json_validate($body)) {
            Sleep::for(config('cyber.gdelt_delay') * 2)->seconds();
            $body = $this->gdelt($params);
        }

        if ($body === null || ! json_validate($body)) {
            Log::warning("Global news: GDELT skipped {$code}", ['body' => mb_substr((string) $body, 0, 200)]);

            return [];
        }

        return collect(json_decode($body, true)['articles'] ?? [])
            ->filter(fn ($a) => filled($a['url'] ?? null) && filled($a['title'] ?? null))
            ->map(fn ($a) => new ItemData(
                url: $a['url'],
                title: $a['title'],
                publisher: $a['domain'] ?? null,
                publishedAt: isset($a['seendate']) ? Carbon::parse($a['seendate']) : null,
                language: $a['language'] ?? null,
                countryCode: $code,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function gdelt(array $params): ?string
    {
        try {
            return $this->http()->get('https://api.gdeltproject.org/api/v2/doc/doc', $params)->body();
        } catch (\Throwable $e) {
            Log::warning('Global news: GDELT request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function orKeywords(): string
    {
        return collect(config('cyber.keywords'))
            ->map(fn ($k) => str_contains($k, ' ') ? "\"{$k}\"" : $k)
            ->implode(' OR ');
    }
}
