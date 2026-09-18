<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $status
 * @property string|null $body_en
 * @property string|null $body_km
 * @property string|null $error
 * @property Carbon|null $generated_at
 */
#[Fillable(['date', 'status', 'body_en', 'body_km', 'error', 'generated_at'])]
class Briefing extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'generated_at' => 'datetime',
        ];
    }
}
