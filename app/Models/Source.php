<?php

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property array<string, mixed>|null $state
 * @property int $interval_minutes
 * @property bool $enabled
 * @property bool $ai_enabled
 * @property Carbon|null $last_fetched_at
 * @property Carbon|null $last_success_at
 * @property string|null $last_error
 * @property int $consecutive_failures
 * @property int $last_item_count
 */
#[Fillable(['name', 'type', 'config', 'interval_minutes', 'enabled', 'ai_enabled'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    public const UNHEALTHY_AFTER_FAILURES = 3;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'state' => 'array',
            'enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function isDue(): bool
    {
        return $this->enabled
            && $this->type !== 'manual'
            && ($this->last_fetched_at === null || $this->last_fetched_at->addMinutes($this->interval_minutes)->isPast());
    }

    public function isHealthy(): bool
    {
        return $this->consecutive_failures < self::UNHEALTHY_AFTER_FAILURES;
    }
}
