<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->orderBy('id')->get();
        $roles = User::ROLES;

        return view('users.index', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => 'nullable|boolean',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'password' => $validated['password'],
        ]);

        app(ActivityLogService::class)->log(
            module: 'users',
            action: 'create',
            entityType: User::class,
            entityId: (int) $user->id,
            description: 'User created by owner.',
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ]
        );

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = User::ROLES;

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => 'nullable|boolean',
        ]);

        $newRole = $validated['role'];
        $newIsActive = (bool) ($validated['is_active'] ?? false);
        if (
            $user->role === User::ROLE_OWNER &&
            ($newRole !== User::ROLE_OWNER || !$newIsActive)
        ) {
            $activeOwners = User::where('role', User::ROLE_OWNER)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeOwners === 0) {
                return back()->withInput()->withErrors([
                    'role' => 'At least one active owner account is required.',
                ]);
            }
        }

        if (auth()->id() === $user->id && !$newIsActive) {
            return back()->withInput()->withErrors([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
        ];

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $newRole,
            'is_active' => $newIsActive,
        ];

        $user->update($updateData);

        app(ActivityLogService::class)->log(
            module: 'users',
            action: 'update',
            entityType: User::class,
            entityId: (int) $user->id,
            description: 'User updated.',
            oldValues: $oldValues,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
            ]
        );

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        app(ActivityLogService::class)->log(
            module: 'users',
            action: 'reset_password',
            entityType: User::class,
            entityId: (int) $user->id,
            description: 'User password reset by owner.',
            newValues: [
                'email' => $user->email,
                'password_updated' => true,
            ]
        );

        return redirect()->back()->with('success', 'Password reset successfully.');
    }
}
