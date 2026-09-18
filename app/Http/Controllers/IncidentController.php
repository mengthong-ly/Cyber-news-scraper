<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Item;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(Incident::STATUSES)]])['status'] ?? null;

        return Inertia::render('incidents/index', [
            'status' => $status,
            'statuses' => Incident::STATUSES,
            'incidents' => Incident::with('assignee:id,name')
                ->withCount('items')
                ->when($status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'closed'))
                ->latest('updated_at')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Incident $incident) => [
                    'id' => $incident->id,
                    'title' => $incident->title,
                    'status' => $incident->status,
                    'severity' => $incident->severity,
                    'assignee' => $incident->assignee?->name,
                    'items_count' => $incident->items_count,
                    'updated_at' => $incident->updated_at->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Create an incident from one item (from the feed or an alert).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $item = Item::findOrFail($data['item_id']);
        $incident = Incident::create([
            'title' => ($data['title'] ?? null) ?: mb_substr($item->displayTitle(), 0, 255),
            'severity' => max(3, $item->severity),
            'assignee_id' => $request->user()->id,
        ]);
        $incident->items()->attach($item);

        AuditLog::record('incident_created', (string) $incident->id, ['item_id' => $item->id]);

        return to_route('incidents.show', $incident);
    }

    public function show(Incident $incident): Response
    {
        $incident->load(['items' => fn ($q) => $q->latest('published_at'), 'notes.user:id,name', 'assignee:id,name']);
        $countries = Countries::all();

        return Inertia::render('incidents/show', [
            'incident' => [
                'id' => $incident->id,
                'title' => $incident->title,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'summary' => $incident->summary,
                'assignee_id' => $incident->assignee_id,
                'created_at' => $incident->created_at->toIso8601String(),
                'closed_at' => $incident->closed_at?->toIso8601String(),
                'items' => $incident->items->map(fn (Item $item) => ItemController::present($item, $countries)),
                'notes' => $incident->notes->sortByDesc('created_at')->values()->map(fn ($note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'user' => $note->user?->name,
                    'created_at' => $note->created_at->toIso8601String(),
                ]),
            ],
            'statuses' => Incident::STATUSES,
            'analysts' => User::whereIn('role', [Role::Admin, Role::Analyst])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(Incident::STATUSES)],
            'severity' => ['sometimes', 'integer', 'between:1,5'],
            'assignee_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->whereIn('role', [Role::Admin->value, Role::Analyst->value])],
            'summary' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        if (isset($data['status'])) {
            $data['closed_at'] = $data['status'] === 'closed' ? ($incident->closed_at ?? now()) : null;
        }

        $incident->update($data);
        AuditLog::record('incident_updated', (string) $incident->id, $data);

        return back();
    }

    public function attach(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $data = $request->validate(['item_id' => ['required', 'exists:items,id']]);
        $incident->items()->syncWithoutDetaching([$data['item_id']]);
        $incident->touch();

        AuditLog::record('incident_item_added', (string) $incident->id, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to incident.')]);

        return back();
    }

    public function detach(Request $request, Incident $incident, Item $item): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $incident->items()->detach($item);
        AuditLog::record('incident_item_removed', (string) $incident->id, ['item_id' => $item->id]);

        return back();
    }
}
