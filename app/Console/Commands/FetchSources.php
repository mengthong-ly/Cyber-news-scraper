<?php

namespace App\Console\Commands;

use App\Jobs\FetchSource;
use App\Models\Source;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sources:fetch {ids?* : Source IDs (default: all enabled)} {--type= : Only sources of this type}')]
#[Description('Fetch sources now, in this process')]
class FetchSources extends Command
{
    public function handle(): int
    {
        $sources = Source::query()
            ->when($this->argument('ids'), fn ($q, $ids) => $q->whereIn('id', $ids), fn ($q) => $q->where('enabled', true))
            ->when($this->option('type'), fn ($q, $type) => $q->where('type', $type))
            ->where('type', '!=', 'manual')
            ->get();

        foreach ($sources as $source) {
            FetchSource::dispatchSync($source);
            $source->refresh();

            $this->line(sprintf(
                '%-4d %-40s %s',
                $source->id,
                mb_substr($source->name, 0, 40),
                $source->last_error ? "FAILED: {$source->last_error}" : "{$source->last_item_count} new",
            ));
        }

        return self::SUCCESS;
    }
}
