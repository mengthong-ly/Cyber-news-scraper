<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->validate(['action' => ['nullable', 'string', 'max:50']])['action'] ?? null;

        return Inertia::render('admin/audit', [
            'action' => $action,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
            'logs' => AuditLog::with('user:id,name,email')
                ->when($action, fn ($q, $a) => $q->where('action', $a))
                ->latest('id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'user' => $log->user?->email,
                    'action' => $log->action,
                    'subject' => $log->subject,
                    'meta' => $log->meta,
                    'ip' => $log->ip,
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
        ]);
    }
}
