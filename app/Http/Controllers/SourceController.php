<?php

namespace App\Http\Controllers;

use App\Http\Requests\SourceRequest;
use App\Jobs\FetchSource;
use App\Models\AuditLog;
use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sources/index', [
            'sources' => Source::withCount('items')->orderBy('type')->orderBy('name')->get()
                ->map(fn (Source $source) => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'type' => $source->type,
                    'config' => $source->config ?? (object) [],
                    'interval_minutes' => $source->interval_minutes,
                    'enabled' => $source->enabled,
                    'ai_enabled' => $source->ai_enabled,
                    'healthy' => $source->isHealthy(),
                    'last_success_at' => $source->last_success_at?->toIso8601String(),
                    'last_fetched_at' => $source->last_fetched_at?->toIso8601String(),
                    'last_error' => $source->last_error,
                    'last_item_count' => $source->last_item_count,
                    'items_count' => $source->items_count,
                ]),
            'types' => [...array_keys(config('cyber.adapters')), 'manual'],
            'requiredSettings' => SourceRequest::REQUIRED_SETTINGS,
        ]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        $source = Source::create($request->sourceAttributes());
        AuditLog::record('source_created', $source->name, ['source_id' => $source->id]);

        return back();
    }

    public function update(SourceRequest $request, Source $source): RedirectResponse
    {
        $source->update($request->sourceAttributes());
        AuditLog::record('source_updated', $source->name, ['source_id' => $source->id]);

        return back();
    }

    public function destroy(Request $request, Source $source): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        AuditLog::record('source_deleted', $source->name, ['source_id' => $source->id]);
        $source->delete();

        return back();
    }

    public function fetch(Request $request, Source $source): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);
        abort_if($source->type === 'manual', 422);

        FetchSource::dispatch($source);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fetch queued for :name.', ['name' => $source->name])]);

        return back();
    }
}
