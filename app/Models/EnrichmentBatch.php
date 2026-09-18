<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $batch_id
 * @property string $status
 * @property list<int> $item_ids
 */
#[Fillable(['batch_id', 'status', 'item_ids', 'succeeded', 'failed', 'ended_at'])]
class EnrichmentBatch extends Model
{
    protected function casts(): array
    {
        return [
            'item_ids' => 'array',
            'ended_at' => 'datetime',
        ];
    }
}
