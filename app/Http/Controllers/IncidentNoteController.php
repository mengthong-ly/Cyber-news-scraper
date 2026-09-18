<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IncidentNoteController extends Controller
{
    public function store(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($request->user()->canAnalyze(), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $incident->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $incident->touch();

        return back();
    }
}
