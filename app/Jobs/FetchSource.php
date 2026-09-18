<?php

namespace App\Jobs;

use App\Models\Source;
use App\Sources\ItemIngestor;
use App\Sources\SourceAdapter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** The global news source walks ~200 countries at GDELT's pace; queue retry_after is set above this. */
    public int $timeout = 3600;

    /** Failures are recorded on the source and retried on its next scheduled run. */
    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(public Source $source) {}

    public function uniqueId(): string
    {
        return (string) $this->source->id;
    }

    public function handle(ItemIngestor $ingestor): void
    {
        $source = $this->source;
        $source->last_fetched_at = now();

        try {
            $adapterClass = config("cyber.adapters.{$source->type}")
                ?? throw new \InvalidArgumentException("No adapter for source type [{$source->type}]");

            /** @var SourceAdapter $adapter */
            $adapter = app($adapterClass);
            $created = $ingestor->ingest($source, $adapter->fetch($source));

            $source->forceFill([
                'last_success_at' => now(),
                'last_error' => null,
                'consecutive_failures' => 0,
                'last_item_count' => $created,
            ])->save();
        } catch (Throwable $e) {
            $source->forceFill([
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'consecutive_failures' => $source->consecutive_failures + 1,
                'last_item_count' => 0,
            ])->save();

            Log::warning("Source {$source->id} ({$source->name}) failed", ['error' => $e->getMessage()]);
        }
    }
}
