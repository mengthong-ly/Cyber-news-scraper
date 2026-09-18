<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\Models\Item;
use App\Support\Enricher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:khmer-sample {--limit=50} {--output=storage/app/private/khmer-sample.csv}')]
#[Description('Translate a sample of Khmer headlines with Claude into a CSV for Khmer speakers to review (costs API credits)')]
class KhmerTranslationSample extends Command
{
    public function handle(Enricher $enricher): int
    {
        $items = Item::where('language', 'km')->where('ai_allowed', true)->latest()->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->warn('No Khmer items yet. Fetch the Khmer sources first.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Send {$items->count()} headline(s) to Claude (".config('cyber.ai.model').')?', true)) {
            return self::SUCCESS;
        }

        $client = app(Client::class);
        $file = fopen(base_path($this->option('output')), 'w');
        fputcsv($file, ['id', 'publisher', 'khmer_title', 'claude_title_en', 'claude_summary_en', 'reviewer_score_1_to_5', 'reviewer_notes']);

        foreach ($this->output->progressIterator($items) as $item) {
            $message = $client->messages->create(...$enricher->requestParams($item));
            $text = collect($message->content)->firstWhere('type', 'text')?->text;
            $labels = is_string($text) ? json_decode($text, true) : [];

            fputcsv($file, [$item->id, $item->publisher, $item->title, $labels['title_en'] ?? '', $labels['summary_en'] ?? '', '', '']);
        }

        fclose($file);
        $this->info('Wrote '.$this->option('output'));

        return self::SUCCESS;
    }
}
