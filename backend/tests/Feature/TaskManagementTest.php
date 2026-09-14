<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserInWorkspace(string $email = 'user@example.com', string $name = 'User One'): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create([
            'name' => $name.' Workspace',
            'slug' => strtolower(str_replace(' ', '-', $name.'-workspace')),
            'default_currency' => 'USD',
        ]);

        WorkspaceMembership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        return [$user, $workspace];
    }

    protected function createProject(Workspace $workspace, string $name = 'Website Project'): Project
    {
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);

        return Project::factory()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => $name,
        ]);
    }

    protected function createTask(Workspace $workspace, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'project_id' => $this->createProject($workspace)->id,
        ], $attributes));
    }

    public function test_authenticated_user_can_create_a_task(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Build homepage',
            'description' => 'Create the initial homepage layout.',
            'deadline' => '2026-10-01',
            'priority' => 'high',
            'status' => 'in_progress',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Build homepage')
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_unauthenticated_user_cannot_access_task_routes(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    public function test_create_validation_fails_for_invalid_payload(): void
    {
        [$user] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $this->postJson('/api/tasks', [
            'title' => '',
            'priority' => 'urgent',
            'status' => 'blocked',
        ])->assertStatus(422);
    }

    public function test_task_receives_active_workspace_id(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        $this->actingAs($user, 'web');

        $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Workspace task',
            'priority' => 'medium',
            'status' => 'todo',
        ])->assertStatus(201);

        $this->assertDatabaseHas('tasks', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Workspace task',
        ]);
    }

    public function test_task_cannot_use_a_project_from_another_workspace(): void
    {
        [$user] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $project = $this->createProject($otherWorkspace);
        $this->actingAs($user, 'web');

        $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Cross workspace task',
            'priority' => 'low',
            'status' => 'todo',
        ])->assertStatus(422);
    }

    public function test_task_cannot_use_a_soft_deleted_project(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        $project->delete();
        $this->actingAs($user, 'web');

        $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Deleted project task',
            'priority' => 'low',
            'status' => 'todo',
        ])->assertStatus(422);
    }

    public function test_user_can_list_only_own_workspace_tasks(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $this->createTask($workspace, ['title' => 'Owned task']);
        $this->createTask($otherWorkspace, ['title' => 'Foreign task']);
        $this->actingAs($user, 'web');

        $this->getJson('/api/tasks')->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Owned task');
    }

    public function test_search_status_priority_and_project_filters_work(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace, 'Homepage Project');
        $otherProject = $this->createProject($workspace, 'Other Project');
        $this->createTask($workspace, [
            'project_id' => $project->id,
            'title' => 'Homepage copy',
            'description' => 'Write homepage content',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
        $this->createTask($workspace, [
            'project_id' => $otherProject->id,
            'title' => 'Other task',
            'status' => 'todo',
            'priority' => 'low',
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/tasks?search=homepage&status=in_progress&priority=high&project_id='.$project->id)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Homepage copy');
    }

    public function test_pagination_works_for_task_listing(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        Task::factory()->count(12)->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/tasks?page=1&per_page=5')->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_view_and_update_own_task(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $task = $this->createTask($workspace, ['title' => 'Original task']);
        $this->actingAs($user, 'web');

        $this->getJson('/api/tasks/'.$task->id)->assertStatus(200)
            ->assertJsonPath('data.id', $task->id);

        $this->putJson('/api/tasks/'.$task->id, [
            'title' => 'Updated task',
            'priority' => 'high',
            'status' => 'done',
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated task')
            ->assertJsonPath('data.status', 'done');
    }

    public function test_update_cannot_switch_to_foreign_or_deleted_project(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $task = $this->createTask($workspace);
        $otherWorkspace = Workspace::factory()->create();
        $foreignProject = $this->createProject($otherWorkspace);
        $deletedProject = $this->createProject($workspace);
        $deletedProject->delete();
        $this->actingAs($user, 'web');

        $this->patchJson('/api/tasks/'.$task->id, ['project_id' => $foreignProject->id])
            ->assertStatus(422);
        $this->patchJson('/api/tasks/'.$task->id, ['project_id' => $deletedProject->id])
            ->assertStatus(422);
    }

    public function test_user_cannot_view_update_or_delete_another_workspace_task(): void
    {
        [$user] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $task = $this->createTask($otherWorkspace);
        $this->actingAs($user, 'web');

        $this->getJson('/api/tasks/'.$task->id)->assertStatus(403);
        $this->putJson('/api/tasks/'.$task->id, ['title' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/api/tasks/'.$task->id)->assertStatus(403);
    }

    public function test_user_can_soft_delete_task_and_deleted_tasks_are_hidden(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $task = $this->createTask($workspace);
        $this->actingAs($user, 'web');

        $this->deleteJson('/api/tasks/'.$task->id)->assertStatus(200);
        $this->assertSoftDeleted($task);
        $this->getJson('/api/tasks')->assertStatus(200)->assertJsonPath('meta.total', 0);
        $this->getJson('/api/tasks/'.$task->id)->assertStatus(404);
    }

    public function test_invalid_status_priority_and_deadline_are_rejected(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');
        $payload = [
            'project_id' => $this->createProject($workspace)->id,
            'title' => 'Invalid task',
            'priority' => 'urgent',
            'status' => 'blocked',
            'deadline' => 'not-a-date',
        ];

        $this->postJson('/api/tasks', $payload)->assertStatus(422);
    }
}