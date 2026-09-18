<?php

namespace App\Models;

use Database\Factories\WatchlistTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $kind
 * @property string $term
 * @property string|null $notes
 */
#[Fillable(['kind', 'term', 'notes'])]
class WatchlistTerm extends Model
{
    /** @use HasFactory<WatchlistTermFactory> */
    use HasFactory;

    /**
     * cambodia: words that make an item Cambodia-related (names, places, Khmer terms).
     * domain:   Cambodian or ministry domains; a hit marks the item Cambodia-related and severity 4.
     * product:  systems the ministry runs; a vulnerability mentioning one is severity 4.
     * actor:    threat actors of interest; a hit raises severity by one.
     */
    public const KINDS = ['cambodia', 'domain', 'product', 'actor'];
}
