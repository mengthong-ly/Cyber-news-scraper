<?php

namespace App\Console\Commands;

use App\Jobs\FetchSource;
use App\Models\Source;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sources:dispatch')]
#[Description('Queue a fetch for every enabled source that is due')]
class DispatchDueSources extends Command
{
    public function handle(): int
    {
        $due = Source::where('enabled', true)->where('type', '!=', 'manual')->get()->filter->isDue();

        $due->each(fn (Source $source) => FetchSource::dispatch($source));

        $this->info("Queued {$due->count()} source(s).");

        return self::SUCCESS;
    }
}
