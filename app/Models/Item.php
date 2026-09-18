<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $source_id
 * @property string $kind
 * @property string $url
 * @property string $title
 * @property string|null $title_en
 * @property string|null $excerpt
 * @property string|null $summary_en
 * @property string|null $publisher
 * @property string|null $country_code
 * @property string|null $language
 * @property string $category
 * @property int $severity
 * @property bool $is_cambodia
 * @property list<string>|null $watch_hits
 * @property array{orgs?: list<string>, domains?: list<string>, cves?: list<string>, threat_actors?: list<string>}|null $entities
 * @property bool $ai_allowed
 * @property string $enrichment_status
 * @property Carbon|null $published_at
 */
#[Fillable([
    'source_id', 'submitted_by', 'kind', 'url', 'url_hash', 'content_hash', 'title', 'title_en', 'excerpt', 'summary_en',
    'publisher', 'author', 'country_code', 'language', 'category', 'severity', 'is_cambodia', 'watch_hits', 'entities',
    'ai_allowed', 'enrichment_status', 'enriched_at', 'screenshot_path', 'notes', 'published_at',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, MassPrunable;

    public const KINDS = ['news', 'advisory', 'vulnerability', 'threat', 'social'];

    public const CATEGORIES = ['attack', 'crime', 'policy', 'innovation', 'vulnerability', 'disinformation', 'general'];

    /** Severity at or above which a Cambodia-related item raises an alert. */
    public const ALERT_SEVERITY = 4;

    protected function casts(): array
    {
        return [
            'is_cambodia' => 'boolean',
            'ai_allowed' => 'boolean',
            'watch_hits' => 'array',
            'entities' => 'array',
            'severity' => 'integer',
            'enriched_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return HasOne<Alert, $this>
     */
    public function alert(): HasOne
    {
        return $this->hasOne(Alert::class);
    }

    /**
     * @return BelongsToMany<Incident, $this>
     */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(Incident::class)->withTimestamps();
    }

    public function displayTitle(): string
    {
        return $this->title_en ?: $this->title;
    }

    /**
     * @param  array{country?: ?string, category?: ?string, kind?: ?string, from?: ?string, to?: ?string, q?: ?string, cambodia?: ?string, min_severity?: ?string}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['country'] ?? null, fn ($q, $v) => $q->where('country_code', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($filters['cambodia'] ?? null, fn ($q) => $q->where('is_cambodia', true))
            ->when($filters['min_severity'] ?? null, fn ($q, $v) => $q->where('severity', '>=', (int) $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('published_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('published_at', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$v}%")
                ->orWhere('title_en', 'like', "%{$v}%")
                ->orWhere('summary_en', 'like', "%{$v}%")));
    }

    /**
     * Raw items are kept for a configurable period; items linked to an incident are kept.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(config('cyber.retention_months')))
            ->whereDoesntHave('incidents');
    }
}
