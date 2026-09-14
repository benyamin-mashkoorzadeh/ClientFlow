<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService)
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'data' => $this->dashboardService->summary($user),
        ]);
    }
}