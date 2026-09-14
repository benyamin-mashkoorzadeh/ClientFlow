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
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    protected function createInvoice(Workspace $workspace, Client $client, array $attributes = []): Invoice
    {
        $today = Carbon::today();

        return Invoice::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'issue_date' => $today->copy()->subDays(20),
            'due_date' => $today->copy()->addDays(10),
            'currency_code' => 'USD',
            'subtotal_cents' => 10000,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 10000,
        ], $attributes));
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }

    public function test_dashboard_returns_workspace_scoped_metrics_and_financials(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $foreignWorkspace = Workspace::factory()->create();
        $client = Client::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Current Client']);
        $deletedClient = Client::factory()->create(['workspace_id' => $workspace->id]);
        $deletedClient->delete();
        $foreignClient = Client::factory()->create(['workspace_id' => $foreignWorkspace->id]);

        Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id, 'status' => 'active']);
        Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id, 'status' => 'completed']);
        Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id, 'status' => 'on_hold']);
        Project::factory()->create(['workspace_id' => $foreignWorkspace->id, 'client_id' => $foreignClient->id, 'status' => 'active']);

        $yesterday = Carbon::today()->subDay();
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => Project::where('workspace_id', $workspace->id)->first()->id, 'status' => 'todo', 'deadline' => $yesterday]);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => Project::where('workspace_id', $workspace->id)->first()->id, 'status' => 'in_progress', 'deadline' => Carbon::today()->addDay()]);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => Project::where('workspace_id', $workspace->id)->first()->id, 'status' => 'done', 'deadline' => $yesterday]);
        Task::factory()->create(['workspace_id' => $foreignWorkspace->id, 'project_id' => Project::where('workspace_id', $foreignWorkspace->id)->first()->id, 'status' => 'todo', 'deadline' => $yesterday]);

        $this->createInvoice($workspace, $client, ['status' => 'draft', 'total_cents' => 1000]);
        $this->createInvoice($workspace, $client, ['status' => 'sent', 'total_cents' => 2000, 'due_date' => $yesterday]);
        $this->createInvoice($workspace, $client, ['status' => 'paid', 'total_cents' => 3000, 'due_date' => $yesterday]);
        $this->createInvoice($workspace, $client, ['status' => 'cancelled', 'total_cents' => 4000, 'due_date' => $yesterday]);
        $this->createInvoice($workspace, $client, ['status' => 'paid', 'currency_code' => 'EUR', 'total_cents' => 9000]);
        $this->createInvoice($foreignWorkspace, $foreignClient, ['status' => 'paid', 'total_cents' => 50000]);

        $this->actingAs($user, 'web');
        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.summary.clients.total', 1)
            ->assertJsonPath('data.summary.projects.total', 3)
            ->assertJsonPath('data.summary.projects.active', 1)
            ->assertJsonPath('data.summary.projects.completed', 1)
            ->assertJsonPath('data.summary.projects.on_hold', 1)
            ->assertJsonPath('data.summary.tasks.total', 3)
            ->assertJsonPath('data.summary.tasks.todo', 1)
            ->assertJsonPath('data.summary.tasks.in_progress', 1)
            ->assertJsonPath('data.summary.tasks.done', 1)
            ->assertJsonPath('data.summary.tasks.overdue', 1)
            ->assertJsonPath('data.summary.invoices.total', 5)
            ->assertJsonPath('data.summary.invoices.draft', 1)
            ->assertJsonPath('data.summary.invoices.sent', 1)
            ->assertJsonPath('data.summary.invoices.paid', 2)
            ->assertJsonPath('data.summary.invoices.cancelled', 1)
            ->assertJsonPath('data.summary.invoices.overdue', 1)
            ->assertJsonPath('data.summary.financial.currency_code', 'USD')
            ->assertJsonPath('data.summary.financial.paid_revenue_cents', 3000)
            ->assertJsonPath('data.summary.financial.outstanding_cents', 2000);
    }

    public function test_soft_deleted_records_are_excluded_from_dashboard(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        $task = Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $project->id]);
        $invoice = $this->createInvoice($workspace, $client);
        $client->delete();
        $project->delete();
        $task->delete();
        $invoice->delete();
        $this->actingAs($user, 'web');

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.summary.clients.total', 0)
            ->assertJsonPath('data.summary.projects.total', 0)
            ->assertJsonPath('data.summary.tasks.total', 0)
            ->assertJsonPath('data.summary.invoices.total', 0)
            ->assertJsonCount(0, 'data.recent_clients')
            ->assertJsonCount(0, 'data.recent_projects')
            ->assertJsonCount(0, 'data.upcoming_tasks')
            ->assertJsonCount(0, 'data.recent_invoices');
    }

    public function test_done_and_past_due_invoices_are_not_overdue(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $project->id, 'status' => 'done', 'deadline' => Carbon::yesterday()]);
        $this->createInvoice($workspace, $client, ['status' => 'paid', 'due_date' => Carbon::yesterday()]);
        $this->createInvoice($workspace, $client, ['status' => 'cancelled', 'due_date' => Carbon::yesterday()]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.summary.tasks.overdue', 0)
            ->assertJsonPath('data.summary.invoices.overdue', 0);
    }

    public function test_recent_lists_are_bounded_and_upcoming_tasks_are_nearest_first(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = Client::factory()->create(['workspace_id' => $workspace->id]);
        $project = Project::factory()->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);

        Client::factory()->count(6)->create(['workspace_id' => $workspace->id]);
        Project::factory()->count(6)->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        Invoice::factory()->count(6)->create(['workspace_id' => $workspace->id, 'client_id' => $client->id]);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $project->id, 'title' => 'Later task', 'deadline' => Carbon::today()->addDays(5), 'status' => 'todo']);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $project->id, 'title' => 'Soon task', 'deadline' => Carbon::today(), 'status' => 'in_progress']);
        Task::factory()->create(['workspace_id' => $workspace->id, 'project_id' => $project->id, 'title' => 'Done task', 'deadline' => Carbon::today(), 'status' => 'done']);
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/dashboard')->assertOk();
        $response->assertJsonCount(5, 'data.recent_clients')
            ->assertJsonCount(5, 'data.recent_projects')
            ->assertJsonCount(5, 'data.recent_invoices')
            ->assertJsonCount(2, 'data.upcoming_tasks')
            ->assertJsonPath('data.upcoming_tasks.0.title', 'Soon task')
            ->assertJsonPath('data.upcoming_tasks.1.title', 'Later task');
    }
}