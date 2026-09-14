<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletedRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceUser(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        $this->actingAs($user, 'web');

        return [$user, $workspace];
    }

    public function test_projects_and_invoices_remain_readable_after_their_client_is_deleted(): void
    {
        [, $workspace] = $this->workspaceUser();
        $client = Client::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Historical Client']);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        $invoice = Invoice::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        $client->delete();

        $this->getJson('/api/projects/'.$project->id)->assertOk()
            ->assertJsonPath('data.client.name', 'Historical Client');
        $this->getJson('/api/invoices/'.$invoice->id)->assertOk()
            ->assertJsonPath('data.client.name', 'Historical Client');
        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.recent_projects.0.client.name', 'Historical Client')
            ->assertJsonPath('data.recent_invoices.0.client.name', 'Historical Client');
    }

    public function test_tasks_remain_readable_after_their_project_is_deleted(): void
    {
        [, $workspace] = $this->workspaceUser();
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id, 'name' => 'Historical Project']);
        $task = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'deadline' => now()->addDay(),
            'status' => 'todo',
        ]);
        $project->delete();

        $this->getJson('/api/tasks/'.$task->id)->assertOk()
            ->assertJsonPath('data.project.name', 'Historical Project');
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.upcoming_tasks.0.project.name', 'Historical Project');
    }
}
