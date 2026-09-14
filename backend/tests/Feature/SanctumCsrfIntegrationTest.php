<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SanctumCsrfIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_client_update_after_login_uses_the_current_session_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        $this->withCredentials()->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/clients/1/edit',
        ]);

        $user = User::factory()->create([
            'email' => 'csrf@example.com',
            'password' => 'Password123!',
            'email_verified_at' => now(),
        ]);
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        $client = Client::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Before']);

        $initialCsrf = $this->rememberCookies($this->get('/sanctum/csrf-cookie')->assertNoContent());
        $loginCookies = $this->rememberCookies($this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ], ['X-XSRF-TOKEN' => $initialCsrf['XSRF-TOKEN']])->assertOk());

        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->patchJson('/api/clients/'.$client->id, ['name' => 'After'], [
            'X-XSRF-TOKEN' => $loginCookies['XSRF-TOKEN'],
        ])->assertOk();

        $this->assertSame('After', $client->fresh()->name);

        $this->postJson('/api/clients', ['name' => 'Created in same session'], [
            'X-XSRF-TOKEN' => $loginCookies['XSRF-TOKEN'],
        ])->assertCreated()->assertJsonPath('data.workspace_id', $workspace->id);
        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $csrf = $this->rememberCookies($this->get('/sanctum/csrf-cookie')->assertNoContent());
        $this->assertNotSame($initialCsrf['XSRF-TOKEN'], $csrf['XSRF-TOKEN']);
        $this->patchJson('/api/clients/'.$client->id, ['name' => 'After refresh'], [
            'X-XSRF-TOKEN' => $csrf['XSRF-TOKEN'],
        ])->assertOk();

        $this->patchJson('/api/clients/'.$client->id, ['name' => 'Stale token'], [
            'X-XSRF-TOKEN' => $initialCsrf['XSRF-TOKEN'],
        ])->assertStatus(419);
        $this->assertSame('After refresh', $client->fresh()->name);
    }

    public function test_registration_establishes_a_browser_session_and_accepts_the_first_authenticated_post(): void
    {
        Notification::fake();
        $this->app->instance('env', 'local');
        $this->withCredentials()->withHeaders(['Origin' => 'http://localhost:3000']);

        $csrf = $this->rememberCookies($this->get('/sanctum/csrf-cookie')->assertNoContent());
        $registrationCookies = $this->rememberCookies($this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], ['X-XSRF-TOKEN' => $csrf['XSRF-TOKEN']])->assertCreated());

        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('user.email', 'new@example.com');
        $this->postJson('/api/email/verification-notification', [], [
            'X-XSRF-TOKEN' => $registrationCookies['XSRF-TOKEN'],
        ])->assertOk();
    }

    public function test_spa_guest_request_to_protected_api_returns_json_unauthorized(): void
    {
        $this->get('/api/user', ['Origin' => 'http://localhost:3000'])
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_spa_preflight_allows_credentials_and_csrf_headers_only_from_the_frontend_origin(): void
    {
        $headers = [
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token',
        ];

        $preflight = $this->call('OPTIONS', '/api/clients/1', [], [], [], array_merge($headers, [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]));
        $preflight->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertStringContainsString('x-xsrf-token', strtolower((string) $preflight->headers->get('Access-Control-Allow-Headers')));

        $this->call('OPTIONS', '/api/clients/1', [], [], [], array_merge($headers, [
            'HTTP_ORIGIN' => 'http://untrusted.example',
        ]))->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    private function rememberCookies(TestResponse $response): array
    {
        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        $this->withUnencryptedCookies($cookies);

        return $cookies;
    }
}
