<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
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

    protected function invoicePayload(Client $client, array $overrides = []): array
    {
        return array_replace_recursive([
            'client_id' => $client->id,
            'issue_date' => '2026-09-13',
            'due_date' => '2026-09-27',
            'items' => [[
                'description' => 'Website development',
                'quantity' => 2,
                'unit_price_cents' => 50000,
                'discount_rate' => 10,
                'tax_rate' => 19,
            ]],
        ], $overrides);
    }

    protected function createInvoice(User $user, Client $client, array $overrides = []): Invoice
    {
        $this->actingAs($user, 'web');
        $response = $this->postJson('/api/invoices', $this->invoicePayload($client, $overrides));
        $response->assertStatus(201);

        return Invoice::findOrFail($response->json('data.id'));
    }

    public function test_unauthenticated_user_cannot_access_invoice_routes(): void
    {
        $this->getJson('/api/invoices')->assertStatus(401);
    }

    public function test_authenticated_user_can_create_invoice_with_generated_number_and_items(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'currency_code' => null,
            'notes' => 'Thank you',
            'invoice_number' => 'CLIENT-CONTROLLED',
        ]));

        $response->assertStatus(422);
        $response = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'notes' => 'Thank you',
        ]));
        $response->assertStatus(201)
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.items.0.description', 'Website development');
    }

    public function test_invoice_calculations_are_integer_safe_and_authoritative(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'items' => [
                ['description' => 'One', 'quantity' => 2, 'unit_price_cents' => 50000, 'discount_rate' => 10, 'tax_rate' => 19],
                ['description' => 'Two', 'quantity' => 1, 'unit_price_cents' => 999, 'discount_rate' => 0, 'tax_rate' => 0],
            ],
            'subtotal_cents' => 1,
            'discount_cents' => 1,
            'tax_cents' => 1,
            'total_cents' => 1,
        ]));

        $response->assertStatus(422);
        $response = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'items' => [
                ['description' => 'One', 'quantity' => 2, 'unit_price_cents' => 50000, 'discount_rate' => 10, 'tax_rate' => 19],
                ['description' => 'Two', 'quantity' => 1, 'unit_price_cents' => 999, 'discount_rate' => 0, 'tax_rate' => 0],
            ],
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.subtotal_cents', 100999)
            ->assertJsonPath('data.discount_cents', 10000)
            ->assertJsonPath('data.tax_cents', 17100)
            ->assertJsonPath('data.total_cents', 108099)
            ->assertJsonPath('data.items.0.line_discount_cents', 10000)
            ->assertJsonPath('data.items.0.line_tax_cents', 17100)
            ->assertJsonPath('data.items.0.line_total_cents', 107100);
    }

    public function test_client_ownership_and_date_validation_are_enforced(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $foreignWorkspace = Workspace::factory()->create();
        $foreignClient = $this->createClient($foreignWorkspace);
        $deletedClient = $this->createClient($workspace);
        $deletedClient->delete();
        $this->actingAs($user, 'web');

        $this->postJson('/api/invoices', $this->invoicePayload($foreignClient))->assertStatus(422);
        $this->postJson('/api/invoices', $this->invoicePayload($deletedClient))->assertStatus(422);
        $this->postJson('/api/invoices', $this->invoicePayload($this->createClient($workspace), [
            'due_date' => '2026-09-01',
        ]))->assertStatus(422);
    }

    public function test_invalid_currency_empty_items_and_invalid_rates_are_rejected(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $this->actingAs($user, 'web');
        $client = $this->createClient($workspace);

        $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'currency_code' => 'ABC',
        ]))->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->postJson('/api/invoices', array_replace($this->invoicePayload($client), [
            'items' => [],
        ]))->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'items' => [[
                'description' => 'Bad rate', 'quantity' => 1, 'unit_price_cents' => 100,
                'discount_rate' => 101, 'tax_rate' => 0,
            ]],
        ]))->assertStatus(422);
    }

    public function test_supported_invoice_currency_is_persisted_as_an_iso_code_on_create_and_edit(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $created = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'currency_code' => 'EUR',
        ]))->assertCreated()->assertJsonPath('data.currency_code', 'EUR');
        $invoice = Invoice::findOrFail($created->json('data.id'));
        $this->assertSame('EUR', $invoice->currency_code);

        $this->patchJson('/api/invoices/'.$invoice->id, [
            'currency_code' => 'CHF',
        ])->assertOk()->assertJsonPath('data.currency_code', 'CHF');
        $this->assertSame('CHF', $invoice->fresh()->currency_code);
    }

    public function test_lowercase_currency_is_normalized_and_unsupported_currency_is_rejected(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $this->actingAs($user, 'web');

        $created = $this->postJson('/api/invoices', $this->invoicePayload($client, [
            'currency_code' => 'eur',
        ]))->assertCreated()->assertJsonPath('data.currency_code', 'EUR');
        $invoice = Invoice::findOrFail($created->json('data.id'));

        $this->patchJson('/api/invoices/'.$invoice->id, [
            'currency_code' => 'gbp',
        ])->assertOk()->assertJsonPath('data.currency_code', 'GBP');
        $this->patchJson('/api/invoices/'.$invoice->id, [
            'currency_code' => 'ABC',
        ])->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->patchJson('/api/invoices/'.$invoice->id, [
            'currency_code' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('currency_code');
        $this->assertSame('GBP', $invoice->fresh()->currency_code);
    }

    public function test_workspace_default_currency_is_used_only_when_supported(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $workspace->update(['default_currency' => 'EUR']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/invoices', $this->invoicePayload($client))
            ->assertCreated()->assertJsonPath('data.currency_code', 'EUR');
        $this->postJson('/api/invoices', $this->invoicePayload($client, ['currency_code' => '']))
            ->assertCreated()->assertJsonPath('data.currency_code', 'EUR');

        $workspace->update(['default_currency' => 'ABC']);
        $this->postJson('/api/invoices', $this->invoicePayload($client))
            ->assertStatus(422)->assertJsonValidationErrors('currency_code');
    }

    public function test_invoice_numbers_are_unique_within_a_workspace(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $first = $this->createInvoice($user, $client);
        $second = $this->createInvoice($user, $client);

        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertSame('INV-2026-0002', $second->invoice_number);
    }

    public function test_user_can_list_search_filter_and_paginate_own_invoices(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace, 'Alpha Client');
        $otherClient = $this->createClient($workspace, 'Beta Client');
        $first = $this->createInvoice($user, $client, ['notes' => 'Alpha invoice']);
        $this->createInvoice($user, $otherClient);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$first->id, ['status' => 'sent'])->assertStatus(200);

        $this->getJson('/api/invoices?search=Alpha&status=sent&client_id='.$client->id.'&per_page=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_user_cannot_access_another_workspace_invoice(): void
    {
        [$user] = $this->createUserInWorkspace();
        [$otherUser, $otherWorkspace] = $this->createUserInWorkspace('other@example.com', 'Other User');
        $invoice = $this->createInvoice($otherUser, $this->createClient($otherWorkspace));
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');

        $this->getJson('/api/invoices/'.$invoice->id)->assertStatus(403);
        $this->putJson('/api/invoices/'.$invoice->id, ['notes' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/api/invoices/'.$invoice->id)->assertStatus(403);
        $this->get('/api/invoices/'.$invoice->id.'/pdf')->assertStatus(403);
        $this->getJson('/api/invoices')->assertJsonPath('meta.total', 0);
    }

    public function test_draft_can_be_edited_and_items_replaced(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $this->createClient($workspace));

        $this->patchJson('/api/invoices/'.$invoice->id, [
            'notes' => 'Updated note',
            'items' => [[
                'description' => 'Updated work', 'quantity' => 1, 'unit_price_cents' => 2000,
                'discount_rate' => 0, 'tax_rate' => 0,
            ]],
        ])->assertStatus(200)
            ->assertJsonPath('data.total_cents', 2000)
            ->assertJsonPath('data.items.0.description', 'Updated work');
    }

    public function test_status_transitions_and_material_edit_restrictions_are_enforced(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $invoice = $this->createInvoice($user, $this->createClient($workspace));
        $this->actingAs($user, 'web');

        $this->patchJson('/api/invoices/'.$invoice->id, ['status' => 'sent'])->assertStatus(200);
        $this->patchJson('/api/invoices/'.$invoice->id, ['notes' => 'Not allowed'])->assertStatus(422);
        $this->patchJson('/api/invoices/'.$invoice->id, ['status' => 'paid'])->assertStatus(200);
        $this->patchJson('/api/invoices/'.$invoice->id, ['status' => 'draft'])->assertStatus(422);
    }

    public function test_only_draft_invoices_can_be_deleted(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $draft = $this->createInvoice($user, $this->createClient($workspace));
        $sent = $this->createInvoice($user, $this->createClient($workspace));
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$sent->id, ['status' => 'sent'])->assertStatus(200);

        $this->deleteJson('/api/invoices/'.$draft->id)->assertStatus(200);
        $this->assertSoftDeleted($draft);
        $this->deleteJson('/api/invoices/'.$sent->id)->assertStatus(422);
        $this->getJson('/api/invoices/'.$draft->id)->assertStatus(404);
    }

    public function test_overdue_is_derived_and_not_persisted_as_status(): void
    {
        [$user, $workspace] = $this->createUserInWorkspace();
        $client = $this->createClient($workspace);
        $overdue = $this->createInvoice($user, $client, [
            'issue_date' => '2026-08-01',
            'due_date' => '2026-09-01',
        ]);
        $paid = $this->createInvoice($user, $client, [
            'issue_date' => '2026-08-01',
            'due_date' => '2026-09-01',
        ]);
        $future = $this->createInvoice($user, $client, ['due_date' => '2026-12-01']);
        $this->actingAs($user, 'web');
        $this->patchJson('/api/invoices/'.$paid->id, ['status' => 'sent'])->assertStatus(200);
        $this->patchJson('/api/invoices/'.$paid->id, ['status' => 'paid'])->assertStatus(200);

        $this->getJson('/api/invoices/'.$overdue->id)->assertJsonPath('data.is_overdue', true);
        $this->getJson('/api/invoices/'.$paid->id)->assertJsonPath('data.is_overdue', false);
        $this->getJson('/api/invoices/'.$future->id)->assertJsonPath('data.is_overdue', false);
        $this->assertDatabaseHas('invoices', ['id' => $overdue->id, 'status' => 'draft']);
    }
}
