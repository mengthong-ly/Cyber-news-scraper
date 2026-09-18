<?php

namespace App\Console\Commands;

use App\Models\EnrichmentBatch;
use App\Support\AlertRaiser;
use App\Support\Enricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('enrich:collect')]
#[Description('Apply results of finished Claude message batches')]
class CollectEnrichment extends Command
{
    public function handle(Enricher $enricher, AlertRaiser $alerts): int
    {
        foreach (EnrichmentBatch::where('status', 'in_progress')->get() as $batch) {
            try {
                $done = $enricher->collect($batch, $alerts);
                $this->line($batch->batch_id.': '.($done ? "applied ({$batch->succeeded} ok, {$batch->failed} failed)" : 'still processing'));
            } catch (Throwable $e) {
                Log::warning('Collecting enrichment batch failed', ['batch' => $batch->batch_id, 'error' => $e->getMessage()]);
                $this->error($batch->batch_id.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
