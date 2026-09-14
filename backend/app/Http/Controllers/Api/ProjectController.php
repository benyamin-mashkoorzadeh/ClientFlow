<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\CurrencyCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with('client:id,name,company')
            ->where('workspace_id', Auth::user()->activeWorkspace()->id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('projects.name', 'like', "%{$search}%")
                    ->orWhere('projects.description', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $projects = $query->latest('id')->paginate($perPage);

        return response()->json([
            'data' => ProjectResource::collection($projects),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'last_page' => $projects->lastPage(),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $workspace = Auth::user()->activeWorkspace();
        $data = $request->validated();
        $data['workspace_id'] = $workspace->id;
        $data['currency_code'] ??= CurrencyCode::workspaceDefault($workspace);

        $project = Project::create($data)->load('client:id,name,company');

        return response()->json(['data' => new ProjectResource($project)], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => new ProjectResource($project->load('client:id,name,company')),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();
        if (array_key_exists('currency_code', $data) && is_null($data['currency_code'])) {
            $data['currency_code'] = CurrencyCode::workspaceDefault(Auth::user()->activeWorkspace());
        }

        $project->update($data);

        return response()->json([
            'data' => new ProjectResource($project->fresh()->load('client:id,name,company')),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }
}
