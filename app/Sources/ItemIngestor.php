<?php

namespace App\Sources;

use App\Models\Item;
use App\Models\Source;
use App\Models\WatchlistTerm;
use App\Support\AlertRaiser;
use Illuminate\Support\Str;

class ItemIngestor
{
    /** @var array<string, list<string>>|null */
    private ?array $watchlist = null;

    public function __construct(private AlertRaiser $alerts) {}

    /**
     * Store new items and return how many were created. Existing URLs and near-duplicate titles are skipped.
     *
     * @param  iterable<ItemData>  $items
     */
    public function ingest(?Source $source, iterable $items, ?int $submittedBy = null, bool $aiAllowed = true): int
    {
        $created = 0;

        foreach ($items as $data) {
            $item = $this->store($source, $data, $submittedBy, $aiAllowed);

            if ($item !== null) {
                $created++;
                $this->alerts->evaluate($item);
            }
        }

        return $created;
    }

    public function store(?Source $source, ItemData $data, ?int $submittedBy = null, bool $aiAllowed = true): ?Item
    {
        $url = self::normalizeUrl($data->url);
        $title = trim($data->title);

        if ($url === '' || $title === '') {
            return null;
        }

        if ($source?->setting('cyber_only') && ! self::isCyberTopic($title.' '.$data->excerpt)) {
            return null;
        }

        $urlHash = sha1($url);
        $contentHash = sha1(Str::of($title)->lower()->replaceMatches('/[^\pL\pN]+/u', '')->toString());

        $isDuplicate = Item::where('url_hash', $urlHash)
            ->orWhere(fn ($q) => $q->where('content_hash', $contentHash)->where('created_at', '>=', now()->subDays(7)))
            ->exists();

        if ($isDuplicate) {
            return null;
        }

        // Threat-intel URLs (e.g. malware hosts) are evidence; for news the URL is just where it was published.
        $text = mb_strtolower($title.' '.$data->excerpt.($data->kind === 'threat' ? ' '.$url : ''));
        $hits = $this->matchWatchlist($text, $data->kind);

        // A product hit means the vulnerability affects systems the ministry runs, so it is relevant to Cambodia.
        $isCambodia = $data->isCambodia || $data->countryCode === 'KH' || $hits['cambodia'] !== [] || $hits['domain'] !== [] || $hits['product'] !== [];
        $category = $data->category ?? self::categorize($title.' '.$data->excerpt);
        $severity = $data->severity ?? self::baseSeverity($category);

        if ($hits['domain'] !== []) {
            $severity = max($severity, 4);
        }

        // Affecting our own products or naming a tracked actor makes an item one step more serious.
        if ($hits['product'] !== [] || $hits['actor'] !== []) {
            $severity = min(5, $severity + 1);
        }

        $allHits = array_values(array_unique(array_merge(...array_values($hits))));
        $language = $data->language ?? (preg_match('/\p{Khmer}/u', $title) ? 'km' : null);
        $aiAllowed = $aiAllowed && ($source === null || $source->ai_enabled);

        return Item::create([
            'source_id' => $source?->id,
            'submitted_by' => $submittedBy,
            'kind' => $data->kind,
            'url' => $url,
            'url_hash' => $urlHash,
            'content_hash' => $contentHash,
            'title' => mb_substr($title, 0, 500),
            'excerpt' => $data->excerpt,
            'publisher' => $data->publisher ? mb_substr($data->publisher, 0, 255) : null,
            'author' => $data->author ? mb_substr($data->author, 0, 255) : null,
            'country_code' => $data->countryCode ?? ($isCambodia ? 'KH' : null),
            'language' => $language,
            'category' => $category,
            'severity' => $severity,
            'is_cambodia' => $isCambodia,
            'watch_hits' => $allHits ?: null,
            'entities' => $data->entities,
            'ai_allowed' => $aiAllowed,
            'enrichment_status' => $aiAllowed && $this->needsEnrichment($data, $isCambodia, $allHits, $language) ? 'pending' : 'skipped',
            'published_at' => $data->publishedAt ?? now(),
        ]);
    }

    public static function categorize(string $text): string
    {
        $text = ' '.mb_strtolower($text).' ';

        foreach (config('cyber.categories') as $category => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    public static function isCyberTopic(string $text): bool
    {
        $text = mb_strtolower($text);

        foreach (config('cyber.topic_terms') as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Tracking fragments (e.g. #utm_campaign=onpage) would defeat de-duplication.
        return preg_replace('/#.*$/', '', $url) ?? $url;
    }

    private static function baseSeverity(string $category): int
    {
        return match ($category) {
            'ransomware', 'data_breach', 'ddos', 'attack' => 3,
            'malware', 'phishing', 'scam',
            'crime', 'vulnerability', 'disinformation' => 2,
            default => 1,
        };
    }

    /**
     * @param  list<string>  $hits
     */
    private function needsEnrichment(ItemData $data, bool $isCambodia, array $hits, ?string $language): bool
    {
        // Keyword pre-filter keeps AI cost down: only items that matter to Cambodia, match a watchlist,
        // need translation, or are threat/social signals.
        return $isCambodia || $hits !== [] || $language === 'km' || in_array($data->kind, ['threat', 'social', 'advisory'], true);
    }

    /**
     * @return array{cambodia: list<string>, domain: list<string>, product: list<string>, actor: list<string>}
     */
    private function matchWatchlist(string $text, string $kind): array
    {
        $this->watchlist ??= WatchlistTerm::all(['kind', 'term'])
            ->groupBy('kind')
            ->map(fn ($terms) => $terms->pluck('term')->map(fn ($t) => mb_strtolower($t))->all())
            ->all();

        $hits = ['cambodia' => [], 'domain' => [], 'product' => [], 'actor' => []];

        foreach ($hits as $group => $_) {
            // Product matches only matter for vulnerability items.
            if ($group === 'product' && $kind !== 'vulnerability') {
                continue;
            }

            foreach ($this->watchlist[$group] ?? [] as $term) {
                if (self::containsTerm($text, $term)) {
                    $hits[$group][] = $term;
                }
            }
        }

        return $hits;
    }

    private static function containsTerm(string $text, string $term): bool
    {
        // Latin terms need word boundaries ("kh" must not match "khan"); Khmer has no spaces, so use substring.
        if (preg_match('/^[\x20-\x7E]+$/', $term)) {
            return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($term, '/').'(?![a-z0-9])/i', $text);
        }

        return str_contains($text, $term);
    }
}
