<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Briefing;
use App\Support\BriefingWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    public function index(): Response
    {
        $latest = Briefing::latest('date')->first();

        return $latest ? $this->show($latest) : Inertia::render('briefings/show', [
            'briefing' => null,
            'history' => [],
            'aiEnabled' => (bool) config('cyber.ai.enabled'),
        ]);
    }

    public function show(Briefing $briefing): Response
    {
        return Inertia::render('briefings/show', [
            'briefing' => [
                'id' => $briefing->id,
                'date' => $briefing->date->toDateString(),
                'status' => $briefing->status,
                'body_en' => $briefing->body_en,
                'body_km' => $briefing->body_km,
                'error' => $briefing->error,
                'generated_at' => $briefing->generated_at?->toIso8601String(),
            ],
            'history' => Briefing::latest('date')->limit(30)->get(['id', 'date', 'status'])
                ->map(fn (Briefing $b) => ['id' => $b->id, 'date' => $b->date->toDateString(), 'status' => $b->status]),
            'aiEnabled' => (bool) config('cyber.ai.enabled'),
        ]);
    }

    public function store(Request $request, BriefingWriter $writer): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);
        abort_unless(config('cyber.ai.enabled'), 422, 'AI is disabled.');

        $briefing = $writer->write(now(config('cyber.ai.timezone')));
        AuditLog::record('briefing_generated', $briefing->date->toDateString());

        return to_route('briefings.show', $briefing);
    }
}
