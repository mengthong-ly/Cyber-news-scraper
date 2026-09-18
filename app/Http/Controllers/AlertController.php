<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request): Response
    {
        $showAll = $request->boolean('all');

        $alerts = Alert::with(['item.incidents:id,title', 'acknowledger:id,name'])
            ->when(! $showAll, fn ($q) => $q->whereNull('acknowledged_at'))
            ->orderByDesc('severity')
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Alert $alert) => [
                'id' => $alert->id,
                'severity' => $alert->severity,
                'reason' => $alert->reason,
                'created_at' => $alert->created_at->toIso8601String(),
                'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
                'acknowledged_by' => $alert->acknowledger?->name,
                'item' => ItemController::present($alert->item),
            ]);

        return Inertia::render('alerts/index', [
            'alerts' => $alerts,
            'showAll' => $showAll,
            'openIncidents' => Incident::where('status', '!=', 'closed')->latest()->get(['id', 'title']),
        ]);
    }

    public function acknowledge(Request $request, Alert $alert): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $alert->update(['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        AuditLog::record('alert_acknowledged', (string) $alert->id);

        return back();
    }
}
