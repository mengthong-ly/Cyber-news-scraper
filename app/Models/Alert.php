<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $item_id
 * @property int $severity
 * @property string $reason
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 */
#[Fillable(['item_id', 'severity', 'reason', 'acknowledged_by', 'acknowledged_at'])]
class Alert extends Model
{
    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
