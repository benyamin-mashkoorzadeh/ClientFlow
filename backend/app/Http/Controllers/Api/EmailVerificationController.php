<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    public function notice(): JsonResponse
    {
        return response()->json([
            'message' => 'Email verification required.',
        ], 403);
    }

    public function send(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse|RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        $alreadyVerified = $user->hasVerifiedEmail();

        if (! $alreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $alreadyVerified ? 'Email already verified.' : 'Email verified successfully.',
            ]);
        }

        return redirect()->away(rtrim(config('app.frontend_url'), '/').'/dashboard')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
