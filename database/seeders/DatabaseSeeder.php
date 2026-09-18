<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed sources and the starter watchlist. Create accounts with `php artisan users:create`.
     */
    public function run(): void
    {
        $this->call([SourceSeeder::class, WatchlistSeeder::class]);
    }
}
