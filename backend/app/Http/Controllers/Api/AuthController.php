<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $workspace = Workspace::create([
                'name' => $user->name,
                'slug' => $this->uniqueWorkspaceSlug($user->name),
                'default_currency' => 'USD',
            ]);

            WorkspaceMembership::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'role' => 'owner',
            ]);

            return $user->load('memberships.workspace');
        });

        Auth::guard('web')->login($user);
        event(new Registered($user));

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user,
            'workspace' => $user->activeWorkspace(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'workspace' => $user?->activeWorkspace(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::shouldUse('web');
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::shouldUse('web');

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(): JsonResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'user' => $user,
            'workspace' => $user->activeWorkspace(),
        ]);
    }

    protected function uniqueWorkspaceSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $counter = 1;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
