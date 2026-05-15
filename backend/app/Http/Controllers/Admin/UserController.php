<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with([
            'socialCredentials:id,user_id,provider,account_label',
        ])->withCount(['socialCredentials', 'workspaces']);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->input('status')) {
            if ($status === 'deactivated') {
                $query->whereNotNull('deactivated_at');
            } elseif ($status === 'active') {
                $query->whereNull('deactivated_at');
            }
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function show(User $user)
    {
        $user->load(['socialCredentials:id,user_id,provider,account_label,created_at', 'workspaces:id,owner_id,name']);
        $user->loadCount(['socialCredentials', 'workspaces']);

        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ]);

        $user->update($validated);

        ActivityLogger::log(
            $request->user(),
            'admin.user_updated',
            "Admin updated user \"{$user->name}\" (#{$user->id})",
            'user', $user->id, $user->name,
        );

        return response()->json(['user' => $user->fresh()]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $name = $user->name;
        $id   = $user->id;

        $user->tokens()->delete();
        $user->delete();

        ActivityLogger::log(
            $request->user(),
            'admin.user_deleted',
            "Admin deleted user \"{$name}\" (#{$id})",
        );

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        if (! $user->email || str_ends_with($user->email, '@social.curator.local')) {
            return response()->json(['message' => 'This user has no email address to send a reset link to.'], 422);
        }

        Password::sendResetLink(['email' => $user->email]);

        ActivityLogger::log(
            $request->user(),
            'admin.password_reset',
            "Admin sent password reset to \"{$user->email}\"",
            'user', $user->id, $user->name,
        );

        return response()->json(['message' => 'Password reset link sent to ' . $user->email . '.']);
    }

    public function deactivate(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        if ($user->isDeactivated()) {
            return response()->json(['message' => 'User is already deactivated.'], 422);
        }

        $user->update(['deactivated_at' => now()]);
        $user->tokens()->delete();

        ActivityLogger::log(
            $request->user(),
            'admin.user_deactivated',
            "Admin deactivated user \"{$user->name}\" (#{$user->id})",
            'user', $user->id, $user->name,
        );

        return response()->json(['message' => 'User deactivated.', 'user' => $user->fresh()]);
    }

    public function activate(Request $request, User $user)
    {
        if (! $user->isDeactivated()) {
            return response()->json(['message' => 'User is already active.'], 422);
        }

        $user->update(['deactivated_at' => null]);

        ActivityLogger::log(
            $request->user(),
            'admin.user_activated',
            "Admin reactivated user \"{$user->name}\" (#{$user->id})",
            'user', $user->id, $user->name,
        );

        return response()->json(['message' => 'User activated.', 'user' => $user->fresh()]);
    }
}
