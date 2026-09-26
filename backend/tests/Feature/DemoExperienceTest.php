<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_a_verified_user_workspace_and_realistic_dataset(): void
    {
        $this->seed(DemoWorkspaceSeeder::class);

        $user = User::query()->where('email', config('demo.email'))->firstOrFail();
        $workspace = Workspace::query()->where('slug', config('demo.workspace_slug'))->firstOrFail();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->isDemo());
        $this->assertSame($workspace->id, $user->activeWorkspace()?->id);
        $this->assertSame('owner', $user->activeWorkspaceMembership()?->role);
        $this->assertSame(6, Client::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(8, Project::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(20, Task::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(9, Invoice::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(18, Invoice::query()->where('workspace_id', $workspace->id)->withCount('items')->get()->sum('items_count'));
        $this->assertSame(['cancelled', 'draft', 'paid', 'sent'], Invoice::query()->where('workspace_id', $workspace->id)->distinct()->orderBy('status')->pluck('status')->all());
    }

    public function test_demo_seeder_is_repeatable_and_does_not_touch_real_workspace_data(): void
    {
        $realUser = User::factory()->create(['email' => 'owner@example.com']);
        $realWorkspace = Workspace::factory()->create(['slug' => 'real-workspace']);
        $realWorkspace->memberships()->create(['user_id' => $realUser->id, 'role' => 'owner']);
        $realClient = Client::factory()->create(['workspace_id' => $realWorkspace->id]);

        $this->seed(DemoWorkspaceSeeder::class);
        $this->seed(DemoWorkspaceSeeder::class);

        $demoWorkspace = Workspace::query()->where('slug', config('demo.workspace_slug'))->firstOrFail();
        $this->assertSame(1, User::query()->where('email', config('demo.email'))->count());
        $this->assertSame(1, Workspace::query()->where('slug', config('demo.workspace_slug'))->count());
        $this->assertSame(6, Client::query()->where('workspace_id', $demoWorkspace->id)->count());
        $this->assertSame(8, Project::query()->where('workspace_id', $demoWorkspace->id)->count());
        $this->assertSame(20, Task::query()->where('workspace_id', $demoWorkspace->id)->count());
        $this->assertSame(9, Invoice::query()->where('workspace_id', $demoWorkspace->id)->count());
        $this->assertDatabaseHas('clients', ['id' => $realClient->id, 'workspace_id' => $realWorkspace->id]);
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $realUser->id, 'workspace_id' => $realWorkspace->id]);
    }

    public function test_demo_seeder_rejects_an_existing_user_identifier_without_modifying_that_user(): void
    {
        $user = User::factory()->create(['email' => config('demo.email'), 'name' => 'Existing User']);
        $workspace = Workspace::factory()->create(['slug' => 'existing-workspace']);
        $workspace->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);

        try {
            $this->seed(DemoWorkspaceSeeder::class);
            $this->fail('Expected the demo seeder to reject the existing user identifier.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The reserved demo identifiers are already in use.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Existing User']);
        $this->assertDatabaseHas('workspace_memberships', ['user_id' => $user->id, 'workspace_id' => $workspace->id]);
        $this->assertDatabaseMissing('workspaces', ['slug' => config('demo.workspace_slug')]);
    }

    public function test_demo_login_authenticates_only_the_seeded_demo_user(): void
    {
        User::factory()->create(['email' => 'visitor@example.com']);
        $this->seed(DemoWorkspaceSeeder::class);

        $demo = User::query()->where('email', config('demo.email'))->firstOrFail();
        $response = $this->postJson('/api/demo/login');

        $response->assertOk()
            ->assertJsonPath('user.id', $demo->id)
            ->assertJsonPath('user.email', config('demo.email'))
            ->assertJsonPath('workspace.slug', config('demo.workspace_slug'));
        $this->assertAuthenticatedAs($demo, 'web');
    }

    public function test_demo_login_fails_cleanly_when_the_seeded_account_is_unavailable(): void
    {
        User::factory()->create(['email' => 'visitor@example.com']);

        $this->postJson('/api/demo/login')
            ->assertStatus(503)
            ->assertJsonPath('message', 'The demo workspace is not available right now.');
        $this->assertGuest('web');
    }

    public function test_normal_password_login_still_works(): void
    {
        $user = User::factory()->create([
            'email' => 'normal@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/login', [
            'email' => 'normal@example.com',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_demo_user_cannot_delete_shared_demo_records(): void
    {
        $this->seed(DemoWorkspaceSeeder::class);
        $user = User::query()->where('email', config('demo.email'))->firstOrFail();
        $workspaceId = $user->activeWorkspace()->id;
        $this->actingAs($user, 'web');

        $this->deleteJson('/api/clients/'.Client::query()->where('workspace_id', $workspaceId)->value('id'))->assertForbidden();
        $this->deleteJson('/api/projects/'.Project::query()->where('workspace_id', $workspaceId)->value('id'))->assertForbidden();
        $this->deleteJson('/api/tasks/'.Task::query()->where('workspace_id', $workspaceId)->value('id'))->assertForbidden();
        $this->deleteJson('/api/invoices/'.Invoice::query()->where('workspace_id', $workspaceId)->value('id'))->assertForbidden();

        $this->assertSame(6, Client::query()->where('workspace_id', $workspaceId)->count());
        $this->assertSame(8, Project::query()->where('workspace_id', $workspaceId)->count());
        $this->assertSame(20, Task::query()->where('workspace_id', $workspaceId)->count());
        $this->assertSame(9, Invoice::query()->where('workspace_id', $workspaceId)->count());
    }
}
