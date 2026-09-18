<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\Item;
use App\Models\Source;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $since = now()->subDays(7);
        $recent = fn () => Item::where('created_at', '>=', $since);
        $countries = Countries::all();

        $trend = Item::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->where('is_cambodia', true)
            ->get(['created_at', 'severity'])
            ->groupBy(fn (Item $item) => $item->created_at->toDateString())
            ->map->count();

        $actors = $recent()->whereNotNull('entities')->where('is_cambodia', true)->pluck('entities')
            ->flatMap(fn ($entities) => $entities['threat_actors'] ?? [])
            ->countBy()
            ->sortDesc()
            ->take(8);

        $sources = Source::where('enabled', true)->where('type', '!=', 'manual')->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'items' => $recent()->count(),
                'cambodia' => $recent()->where('is_cambodia', true)->count(),
                'openAlerts' => Alert::whereNull('acknowledged_at')->count(),
                'openIncidents' => Incident::where('status', '!=', 'closed')->count(),
                'unhealthySources' => $sources->reject->isHealthy()->count(),
                'sources' => $sources->count(),
            ],
            'byCategory' => $recent()->where('is_cambodia', true)
                ->select('category', DB::raw('count(*) as total'))
                ->groupBy('category')->pluck('total', 'category'),
            'trend' => collect(range(13, 0))->map(fn ($daysAgo) => [
                'date' => $date = now()->subDays($daysAgo)->toDateString(),
                'total' => $trend[$date] ?? 0,
            ]),
            'topCountries' => $recent()->whereNotNull('country_code')
                ->select('country_code', DB::raw('count(*) as total'))
                ->groupBy('country_code')->orderByDesc('total')->limit(8)->get()
                ->map(fn ($row) => ['code' => $row->country_code, 'name' => $countries[$row->country_code] ?? $row->country_code, 'total' => $row->total]),
            'topActors' => $actors->map(fn ($total, $name) => ['name' => $name, 'total' => $total])->values(),
            'latestAlerts' => Alert::with('item:id,title,title_en,url,publisher')->whereNull('acknowledged_at')
                ->latest()->limit(5)->get()
                ->map(fn (Alert $alert) => [
                    'id' => $alert->id,
                    'severity' => $alert->severity,
                    'reason' => $alert->reason,
                    'title' => $alert->item->displayTitle(),
                    'url' => $alert->item->url,
                    'created_at' => $alert->created_at->toIso8601String(),
                ]),
            'lastFetchedAt' => Source::max('last_success_at'),
        ]);
    }
}
