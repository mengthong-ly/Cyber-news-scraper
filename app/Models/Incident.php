<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 * @property int $severity
 * @property int|null $assignee_id
 * @property string|null $summary
 * @property Carbon|null $closed_at
 */
#[Fillable(['title', 'status', 'severity', 'assignee_id', 'summary', 'closed_at'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    public const STATUSES = ['open', 'investigating', 'closed'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    /**
     * @return BelongsToMany<Item, $this>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->withTimestamps();
    }

    /**
     * @return HasMany<IncidentNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
