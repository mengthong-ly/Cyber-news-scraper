<?php

namespace App\Console\Commands;

use App\Support\BriefingWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('briefing:generate')]
#[Description("Write today's briefing in English and Khmer")]
class GenerateBriefing extends Command
{
    public function handle(BriefingWriter $writer): int
    {
        if (! config('cyber.ai.enabled')) {
            $this->warn('AI is disabled (CYBER_AI_ENABLED=false).');

            return self::SUCCESS;
        }

        $briefing = $writer->write(now(config('cyber.ai.timezone')));
        $this->info("Briefing {$briefing->date->toDateString()}: {$briefing->status}".($briefing->error ? " ({$briefing->error})" : ''));

        return $briefing->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
