<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
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

    protected function createClient(Workspace $workspace, string $name = 'Acme Client'): Client
    {
        return Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
        ]);
    }

    protected function createProject(Workspace $workspace, array $attributes = []): Project
    {
        return Project::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'client_id' => $this->createClient($workspace)->id,
        ], $attributes));
    }

    public function test_authenticated_user_can_create_a_project(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Website Redesign',
            'description' => 'Refresh the client website.',
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-01',
            'budget_cents' => 125000,
            'currency_code' => 'USD',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Website Redesign')
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.budget_cents', 125000);
    }

    public function test_unauthenticated_user_cannot_access_project_routes(): void
    {
        $this->getJson('/api/projects')->assertStatus(401);
    }

    public function test_create_validation_fails_for_invalid_payload(): void
    {
        [$user] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'name' => '',
            'status' => 'invalid',
            'budget_cents' => -1,
        ])->assertStatus(422);
    }

    public function test_project_receives_active_workspace_id_and_workspace_currency(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $workspace->update(['default_currency' => 'EUR']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Currency Project',
            'status' => 'active',
        ])->assertStatus(201)
            ->assertJsonPath('data.currency_code', 'EUR');

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Currency Project',
        ]);
    }

    public function test_project_create_accepts_supported_iso_currency_and_normalizes_lowercase(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $created = $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Euro Project',
            'status' => 'active',
            'currency_code' => 'EUR',
        ])->assertCreated()->assertJsonPath('data.currency_code', 'EUR');
        $this->assertSame('EUR', Project::findOrFail($created->json('data.id'))->currency_code);

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Lowercase Project',
            'status' => 'active',
            'currency_code' => 'gbp',
        ])->assertCreated()->assertJsonPath('data.currency_code', 'GBP');
    }

    public function test_project_edit_preserves_saved_currency_until_explicitly_changed(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace, ['currency_code' => 'EUR']);
        $this->actingAs($user, 'web');

        $this->patchJson('/api/projects/'.$project->id, ['name' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.currency_code', 'EUR');
        $this->patchJson('/api/projects/'.$project->id, ['currency_code' => 'cad'])
            ->assertOk()->assertJsonPath('data.currency_code', 'CAD');
        $this->assertSame('CAD', $project->fresh()->currency_code);
    }

    public function test_unsupported_project_currency_is_rejected_on_create_and_edit(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $project = $this->createProject($workspace, ['currency_code' => 'EUR']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Unsupported',
            'status' => 'active',
            'currency_code' => 'ABC',
        ])->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->patchJson('/api/projects/'.$project->id, ['currency_code' => 'ABC'])
            ->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->assertSame('EUR', $project->fresh()->currency_code);
    }

    public function test_unsupported_workspace_default_cannot_bypass_project_currency_validation(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $workspace->update(['default_currency' => 'ABC']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Unsupported Default',
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('currency_code');
    }

    public function test_project_cannot_use_a_client_from_another_workspace(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $client = $this->createClient($otherWorkspace, 'Foreign Client');
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Cross Workspace Project',
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_project_cannot_use_a_soft_deleted_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $client->delete();
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $client->id,
            'name' => 'Deleted Client Project',
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_user_can_list_only_own_workspace_projects(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $this->createProject($workspace, ['name' => 'Owned Project']);
        $this->createProject($otherWorkspace, ['name' => 'Foreign Project']);
        $this->actingAs($user, 'web');

        $this->getJson('/api/projects')->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Owned Project');
    }

    public function test_search_status_and_client_filters_work(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $alpha = $this->createClient($workspace, 'Alpha Client');
        $beta = $this->createClient($workspace, 'Beta Client');
        $this->createProject($workspace, [
            'client_id' => $alpha->id,
            'name' => 'Alpha Website',
            'description' => 'Marketing site',
            'status' => 'active',
        ]);
        $this->createProject($workspace, [
            'client_id' => $beta->id,
            'name' => 'Beta App',
            'status' => 'completed',
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/projects?search=website&status=active&client_id='.$alpha->id)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alpha Website');
    }

    public function test_pagination_works_for_project_listing(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        Project::factory()->count(12)->create([
            'workspace_id' => $workspace->id,
            'client_id' => $this->createClient($workspace)->id,
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/projects?page=1&per_page=5')->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(5, 'data');
    }

    public function test_user_can_view_and_update_own_project(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace, ['name' => 'Original Project']);
        $this->actingAs($user, 'web');

        $this->getJson('/api/projects/'.$project->id)->assertStatus(200)
            ->assertJsonPath('data.id', $project->id);

        $this->putJson('/api/projects/'.$project->id, [
            'name' => 'Updated Project',
            'status' => 'completed',
            'budget_cents' => 50000,
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Project')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_update_cannot_switch_to_foreign_or_deleted_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        $otherWorkspace = Workspace::factory()->create();
        $foreignClient = $this->createClient($otherWorkspace);
        $deletedClient = $this->createClient($workspace);
        $deletedClient->delete();
        $this->actingAs($user, 'web');

        $this->patchJson('/api/projects/'.$project->id, ['client_id' => $foreignClient->id])
            ->assertStatus(422);
        $this->patchJson('/api/projects/'.$project->id, ['client_id' => $deletedClient->id])
            ->assertStatus(422);
    }

    public function test_user_cannot_view_update_or_delete_another_workspace_project(): void
    {
        [$user] = $this->createUserInWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $project = $this->createProject($otherWorkspace);
        $this->actingAs($user, 'web');

        $this->getJson('/api/projects/'.$project->id)->assertStatus(403);
        $this->putJson('/api/projects/'.$project->id, ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/api/projects/'.$project->id)->assertStatus(403);
    }

    public function test_user_can_soft_delete_project_and_deleted_projects_are_hidden(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $project = $this->createProject($workspace);
        $this->actingAs($user, 'web');

        $this->deleteJson('/api/projects/'.$project->id)->assertStatus(200);
        $this->assertSoftDeleted($project);
        $this->getJson('/api/projects')->assertStatus(200)->assertJsonPath('meta.total', 0);
        $this->getJson('/api/projects/'.$project->id)->assertStatus(404);
    }

    public function test_end_date_must_not_be_before_start_date(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $this->postJson('/api/projects', [
            'client_id' => $this->createClient($workspace)->id,
            'name' => 'Invalid Dates',
            'status' => 'active',
            'start_date' => '2026-10-01',
            'end_date' => '2026-09-01',
        ])->assertStatus(422);
    }

    public function test_invalid_status_and_budget_are_rejected(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');
        $payload = [
            'client_id' => $this->createClient($workspace)->id,
            'name' => 'Invalid Values',
            'status' => 'paused',
            'budget_cents' => -100,
        ];

        $this->postJson('/api/projects', $payload)->assertStatus(422);
        $payload['status'] = 'active';
        $payload['budget_cents'] = '10.50';
        $this->postJson('/api/projects', $payload)->assertStatus(422);
    }
}
