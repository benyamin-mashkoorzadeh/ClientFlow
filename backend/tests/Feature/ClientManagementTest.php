<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
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

    public function test_authenticated_user_can_create_a_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/clients', [
            'name' => 'Acme Studio',
            'company' => 'Acme',
            'email' => 'hello@acme.com',
            'phone' => '123456',
            'address' => '123 Market St',
            'notes' => 'Important client',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acme Studio')
            ->assertJsonPath('data.workspace_id', $workspace->id);

        $this->assertDatabaseHas('clients', [
            'workspace_id' => $workspace->id,
            'email' => 'hello@acme.com',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_client_routes(): void
    {
        $response = $this->getJson('/api/clients');
        $response->assertStatus(401);
    }

    public function test_create_validation_fails_for_invalid_payload(): void
    {
        [$user] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/clients', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_client_receives_active_workspace_id(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');

        $this->postJson('/api/clients', [
            'name' => 'Northwind',
            'company' => 'Northwind Co',
        ]);

        $this->assertDatabaseHas('clients', [
            'workspace_id' => $workspace->id,
            'name' => 'Northwind',
        ]);
    }

    public function test_user_can_list_only_own_workspace_clients(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('owner@example.com');
        $otherWorkspace = Workspace::factory()->create();

        Client::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'name' => 'Owned Client',
        ]);

        Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Other Workspace Client',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('data.2');
    }

    public function test_user_cannot_see_clients_from_another_workspace(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('owner2@example.com');
        $otherWorkspace = Workspace::factory()->create();
        $client = Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Secret Client',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/clients/' . $client->id);

        $response->assertStatus(403);
    }

    public function test_search_works_for_client_listing(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('search@example.com');
        $this->actingAs($user, 'web');

        Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha Company',
            'company' => 'Alpha Labs',
            'email' => 'alpha@example.com',
        ]);

        Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Beta Company',
            'company' => 'Beta Labs',
            'email' => 'beta@example.com',
        ]);

        $response = $this->getJson('/api/clients?search=alpha');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alpha Company');
    }

    public function test_pagination_works_for_client_listing(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('paginator@example.com');
        $this->actingAs($user, 'web');

        Client::factory()->count(12)->create(['workspace_id' => $workspace->id]);

        $response = $this->getJson('/api/clients?page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 12);
    }

    public function test_client_pagination_rejects_non_positive_page_sizes(): void
    {
        [$user] = $this->createUserInWorkspace('page-size@example.com');
        $this->actingAs($user, 'web');

        $this->getJson('/api/clients?per_page=0')->assertStatus(200)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_user_can_view_own_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('view@example.com');
        $client = Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Viewable Client',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/clients/' . $client->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', 'Viewable Client');
    }

    public function test_user_cannot_update_another_workspaces_client(): void
    {
        [$user] = $this->createUserInWorkspace('otherupdate@example.com');
        $otherWorkspace = Workspace::factory()->create();
        $client = Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Foreign Client',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->putJson('/api/clients/' . $client->id, [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_update_own_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('update@example.com');
        $client = Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Original Name',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->putJson('/api/clients/' . $client->id, [
            'name' => 'Updated Name',
            'company' => 'Updated Corp',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.company', 'Updated Corp');
    }

    public function test_user_can_soft_delete_own_client(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('delete@example.com');
        $client = Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Delete Me',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->deleteJson('/api/clients/' . $client->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted($client);

        $this->assertDatabaseMissing('clients', [
            'id' => $client->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_cannot_delete_another_workspaces_client(): void
    {
        [$user] = $this->createUserInWorkspace('otherdelete@example.com');
        $otherWorkspace = Workspace::factory()->create();
        $client = Client::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Protected Client',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->deleteJson('/api/clients/' . $client->id);

        $response->assertStatus(403);
    }

    public function test_deleted_client_disappears_from_normal_queries(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace('hidden@example.com');
        $client = Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Hidden Client',
        ]);

        $this->actingAs($user, 'web');
        $client->delete();

        $response = $this->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }
}
