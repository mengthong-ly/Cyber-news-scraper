<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WatchlistTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistTermController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('watchlist/index', [
            'terms' => WatchlistTerm::orderBy('kind')->orderBy('term')->get(['id', 'kind', 'term', 'notes']),
            'kinds' => WatchlistTerm::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        // Each kind has its own form on the page, so errors go to a bag named after the kind.
        $bag = in_array($request->input('kind'), WatchlistTerm::KINDS, true) ? $request->input('kind') : 'default';

        $data = $request->validateWithBag($bag, [
            'kind' => ['required', Rule::in(WatchlistTerm::KINDS)],
            'term' => ['required', 'string', 'min:2', 'max:255', Rule::unique('watchlist_terms')->where('kind', $request->input('kind'))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $term = WatchlistTerm::create([...$data, 'term' => trim($data['term'])]);
        AuditLog::record('watchlist_added', "{$term->kind}: {$term->term}");

        return back();
    }

    public function destroy(Request $request, WatchlistTerm $term): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        AuditLog::record('watchlist_removed', "{$term->kind}: {$term->term}");
        $term->delete();

        return back();
    }
}
