<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
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

    protected function createInvoice(User $user, Workspace $workspace, array $overrides = []): Invoice
    {
        $client = Client::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'PDF Client',
            'company' => 'PDF Company',
            'email' => 'pdf@example.com',
            'phone' => '555-0100',
            'address' => '100 PDF Street',
        ]);
        $this->actingAs($user, 'web');

        $payload = array_replace_recursive([
            'client_id' => $client->id,
            'issue_date' => '2026-09-13',
            'due_date' => '2026-09-27',
            'notes' => 'Thank you for your business.',
            'items' => [[
                'description' => 'PDF website work',
                'quantity' => 2,
                'unit_price_cents' => 50000,
                'discount_rate' => 10,
                'tax_rate' => 19,
            ]],
        ], $overrides);

        $response = $this->postJson('/api/invoices', $payload);
        $response->assertStatus(201);

        return Invoice::findOrFail($response->json('data.id'));
    }

    public function test_unauthenticated_user_cannot_download_invoice_pdf(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $workspace);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/invoices/'.$invoice->id.'/pdf')->assertStatus(401);
    }

    public function test_authenticated_user_can_download_own_invoice_pdf(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $workspace);
        $this->actingAs($user, 'web');

        $response = $this->get('/api/invoices/'.$invoice->id.'/pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=invoice-'.$invoice->invoice_number.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_cannot_download_foreign_workspace_invoice_pdf(): void
    {
        [$user] = $this->createUserInWorkspace();
        [$otherUser, $otherWorkspace] = $this->createUserInWorkspace('other@example.com', 'Other User');
        $invoice = $this->createInvoice($otherUser, $otherWorkspace);
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');

        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertForbidden();
    }

    public function test_soft_deleted_invoice_cannot_download_pdf(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $workspace);
        $invoice->delete();
        $this->actingAs($user, 'web');

        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertNotFound();
    }

    public function test_pdf_generation_does_not_change_invoice_status_or_totals(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $workspace);
        $before = $invoice->only(['status', 'subtotal_cents', 'discount_cents', 'tax_cents', 'total_cents']);
        $this->actingAs($user, 'web');

        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertOk();

        $this->assertSame($before, Invoice::findOrFail($invoice->id)->only([
            'status', 'subtotal_cents', 'discount_cents', 'tax_cents', 'total_cents',
        ]));
    }

    public function test_pdf_generation_works_for_sent_paid_and_cancelled_invoices(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $sent = $this->createInvoice($user, $workspace);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$sent->id, ['status' => 'sent'])->assertOk();
        $paid = $this->createInvoice($user, $workspace);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$paid->id, ['status' => 'sent'])->assertOk();
        $this->patchJson('/api/invoices/'.$paid->id, ['status' => 'paid'])->assertOk();
        $cancelled = $this->createInvoice($user, $workspace);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$cancelled->id, ['status' => 'cancelled'])->assertOk();

        foreach ([$sent, $paid, $cancelled] as $invoice) {
            $this->actingAs($user, 'web');
            $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertOk();
        }
    }

    public function test_overdue_invoice_pdf_is_read_only_and_does_not_persist_overdue_status(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $workspace, [
            'issue_date' => '2026-08-01',
            'due_date' => '2026-09-01',
        ]);
        $this->actingAs($user, 'web');

        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'draft',
        ]);
    }
}