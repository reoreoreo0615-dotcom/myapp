<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeleteUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    /**
     * Display the user management list.
     */
    public function index(): Response
    {
        $users = User::query()
            ->withCount('workouts')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'created_at' => $user->created_at,
                'workouts_count' => $user->workouts_count,
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Grant or revoke admin privileges for a user.
     */
    public function updateAdmin(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $revokingLastAdmin = $user->is_admin
            && ! $validated['is_admin']
            && User::where('is_admin', true)->count() <= 1;

        if ($revokingLastAdmin) {
            return Redirect::route('admin.users.index')
                ->with('error', '最後の管理者から管理者権限を剥奪することはできません。');
        }

        $user->is_admin = $validated['is_admin'];
        $user->save();

        return Redirect::route('admin.users.index');
    }

    /**
     * Delete a user and all of their data.
     */
    public function destroy(Request $request, User $user, DeleteUserService $deleteUserService): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return Redirect::route('admin.users.index')
                ->with('error', '自分自身を削除することはできません。');
        }

        $deleteUserService->delete($user);

        return Redirect::route('admin.users.index');
    }
}
