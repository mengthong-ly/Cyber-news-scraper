<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Item;
use App\Sources\ItemData;
use App\Sources\ItemIngestor;
use App\Support\AlertRaiser;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    private const FILTERS = ['country', 'category', 'kind', 'from', 'to', 'q', 'cambodia', 'min_severity'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        if (filled($filters['q'])) {
            AuditLog::record('search', $filters['q'], array_filter($filters));
        }

        $countries = Countries::all();

        $items = Item::filter($filters)
            ->with('incidents:id,title')
            ->latest('published_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Item $item) => $this->present($item, $countries));

        return Inertia::render('items/index', [
            'items' => $items,
            'filters' => $filters,
            'countries' => $countries,
            'categories' => Item::CATEGORIES,
            'kinds' => Item::KINDS,
            'openIncidents' => Incident::where('status', '!=', 'closed')->latest()->get(['id', 'title']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('items/create', ['categories' => Item::CATEGORIES]);
    }

    /**
     * Manual intake: an analyst records a public post they found (e.g. on Facebook or TikTok).
     */
    public function store(StoreItemRequest $request, ItemIngestor $ingestor, AlertRaiser $alerts): RedirectResponse
    {
        $data = $request->validated();

        $item = $ingestor->store(null, new ItemData(
            url: $data['url'],
            title: $data['title'],
            kind: 'social',
            excerpt: $data['excerpt'] ?? null,
            publisher: 'Manual: '.$data['platform'],
            category: $data['category'] ?? null,
            severity: $data['severity'] ?? null,
            isCambodia: (bool) ($data['is_cambodia'] ?? false),
        ), $request->user()->id, (bool) ($data['ai_allowed'] ?? true));

        if ($item === null) {
            return back()->withErrors(['url' => __('This link (or the same headline) is already recorded.')]);
        }

        $item->update([
            'notes' => $data['notes'] ?? null,
            'screenshot_path' => $request->file('screenshot')?->store('screenshots'),
        ]);

        $alerts->evaluate($item);
        AuditLog::record('item_submitted', $item->url, ['item_id' => $item->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item recorded.')]);

        return to_route('items.index');
    }

    /**
     * Analyst correction of the labels.
     */
    public function update(Request $request, Item $item, AlertRaiser $alerts): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $data = $request->validate([
            'category' => ['sometimes', Rule::in(Item::CATEGORIES)],
            'severity' => ['sometimes', 'integer', 'between:1,5'],
            'is_cambodia' => ['sometimes', 'boolean'],
            'reviewed' => ['sometimes', 'boolean'],
        ]);

        $item->fill(collect($data)->except('reviewed')->all());

        if ($data['reviewed'] ?? false) {
            $item->enrichment_status = 'done';
        }

        $item->save();
        $alerts->evaluate($item);
        AuditLog::record('item_updated', $item->url, ['item_id' => $item->id, 'changes' => $data]);

        return back();
    }

    public function screenshot(Item $item): StreamedResponse
    {
        abort_if($item->screenshot_path === null, 404);

        return Storage::disk('local')->download($item->screenshot_path);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $countries = Countries::all();

        AuditLog::record('export', null, array_filter($filters));

        return response()->streamDownload(function () use ($filters, $countries) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Published', 'Country', 'Kind', 'Category', 'Severity', 'Cambodia', 'Title', 'Title (English)', 'Summary', 'Source', 'URL']);

            Item::filter($filters)->latest('published_at')->lazy()->each(fn (Item $item) => fputcsv($out, [
                $item->published_at?->toDateTimeString(),
                $countries[$item->country_code] ?? $item->country_code,
                $item->kind,
                $item->category,
                $item->severity,
                $item->is_cambodia ? 'yes' : 'no',
                self::csvSafe($item->title),
                self::csvSafe($item->title_en),
                self::csvSafe($item->summary_en),
                self::csvSafe($item->publisher),
                $item->url,
            ]));

            fclose($out);
        }, 'cyber-watch-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, string>  $countries
     * @return array<string, mixed>
     */
    public static function present(Item $item, array $countries = []): array
    {
        return [
            'id' => $item->id,
            'url' => $item->url,
            'title' => $item->title,
            'title_en' => $item->title_en,
            'summary' => $item->summary_en ?? $item->excerpt,
            'publisher' => $item->publisher,
            'kind' => $item->kind,
            'country' => $item->country_code ? ($countries[$item->country_code] ?? $item->country_code) : null,
            'language' => $item->language,
            'category' => $item->category,
            'severity' => $item->severity,
            'is_cambodia' => $item->is_cambodia,
            'entities' => $item->entities,
            'watch_hits' => $item->watch_hits,
            'enrichment_status' => $item->enrichment_status,
            'has_screenshot' => $item->screenshot_path !== null,
            'notes' => $item->notes,
            'incidents' => $item->relationLoaded('incidents') ? $item->incidents->map->only('id', 'title') : [],
            'published_at' => $item->published_at?->toIso8601String(),
        ];
    }

    /**
     * Spreadsheet apps execute cells starting with = + - @ as formulas; source text is untrusted.
     */
    private static function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * @return array<string, ?string>
     */
    private function filters(Request $request): array
    {
        $valid = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'category' => ['nullable', Rule::in(Item::CATEGORIES)],
            'kind' => ['nullable', Rule::in(Item::KINDS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'cambodia' => ['nullable', 'in:1'],
            'min_severity' => ['nullable', 'integer', 'between:1,5'],
        ]);

        return array_merge(array_fill_keys(self::FILTERS, null), $valid);
    }
}
