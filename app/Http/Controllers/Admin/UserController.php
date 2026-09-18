<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'created_at' => $user->created_at->toIso8601String(),
            ]),
            'roles' => array_column(Role::cases(), 'value'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());
        $user->forceFill(['email_verified_at' => now()])->save();

        AuditLog::record('user_created', $user->email, ['role' => $user->role->value]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created. Share the password securely; they must set up two-factor authentication at first login.')]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        if ($user->is($request->user()) && $data['role'] !== Role::Admin->value) {
            return back()->withErrors(['role' => __('You cannot remove your own admin role.')]);
        }

        $user->update($data);
        AuditLog::record('user_role_changed', $user->email, $data);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => __('You cannot delete your own account here.')]);
        }

        AuditLog::record('user_deleted', $user->email);
        $user->delete();

        return back();
    }
}
