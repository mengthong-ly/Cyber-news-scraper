<?php

namespace App\Console\Commands;

use App\Support\Enricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('enrich:submit')]
#[Description('Send pending items to Claude as a message batch')]
class SubmitEnrichment extends Command
{
    public function handle(Enricher $enricher): int
    {
        if (! config('cyber.ai.enabled')) {
            $this->warn('AI enrichment is disabled (CYBER_AI_ENABLED=false).');

            return self::SUCCESS;
        }

        $batch = $enricher->submit();

        $this->info($batch ? 'Submitted batch '.$batch->batch_id.' with '.count($batch->item_ids).' item(s).' : 'Nothing to enrich.');

        return self::SUCCESS;
    }
}
