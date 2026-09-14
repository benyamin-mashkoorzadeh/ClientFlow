<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\SendingNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthAndWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_registration_creates_user_workspace_and_owner_membership(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonPath('workspace.name', 'Jane Doe');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $workspace = Workspace::query()->firstOrFail();

        $this->assertSame($workspace->id, $user->activeWorkspace()?->id);
        $this->assertDatabaseHas('workspace_memberships', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rolls_back_if_workspace_creation_fails(): void
    {
        Workspace::creating(function () {
            throw new \RuntimeException('Workspace creation failed.');
        });

        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
        $this->assertDatabaseMissing('workspace_memberships', ['user_id' => 1]);
    }

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'jane@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout_success(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200);
        $this->assertGuest();
    }

    public function test_current_user_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_browser_requests_to_protected_api_routes_return_json_unauthorized_without_a_login_route(): void
    {
        $this->get('/api/user')->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->post('/api/email/verification-notification')->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_authenticated_user_endpoint_returns_user_and_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('workspace.id', $workspace->id);
    }

    public function test_forgot_password_and_reset_password_flow(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'http://localhost:3000']);

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        $forgot = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $forgot->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $token = $notification->token;

            $this->assertNotEmpty($token);
            $resetUrl = $notification->toMail($user)->actionUrl;
            $this->assertSame('http://localhost:3000/reset-password', strtok($resetUrl, '?'));
            parse_str(parse_url($resetUrl, PHP_URL_QUERY) ?: '', $query);
            $this->assertSame($token, $query['token'] ?? null);
            $this->assertSame($user->email, $query['email'] ?? null);

            $response = $this->postJson('/api/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_email_verification_is_required_for_verified_routes(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/verified-only');

        $response->assertStatus(403);

        $user->markEmailAsVerified();

        $verifiedResponse = $this->getJson('/api/verified-only');

        $verifiedResponse->assertStatus(200);
    }

    public function test_email_verification_notification_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->postJson('/api/email/verification-notification');

        $response->assertStatus(200);
        Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
            $this->get($notification->toMail($user)->actionUrl)->assertRedirect('http://localhost:3000/dashboard');
            $this->assertNotNull($user->fresh()->email_verified_at);

            return true;
        });
    }

    public function test_signed_verification_link_works_without_a_session_and_returns_to_next(): void
    {
        Event::fake([Verified::class]);
        config(['app.frontend_url' => 'http://localhost:3000']);
        $user = User::factory()->create(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->assertGuest();
        $this->get($url)->assertRedirect('http://localhost:3000/dashboard')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertGuest();
        Event::assertDispatched(Verified::class, fn ($event) => $event->user->is($user));
    }

    public function test_tampered_and_expired_verification_links_are_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $parameters = ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())];
        $validUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters);
        $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);

        $this->get(str_replace('signature=', 'signature=tampered', $validUrl))->assertStatus(403);
        $this->get($expiredUrl)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_validly_signed_link_with_wrong_email_hash_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('different@example.com'),
        ]);

        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->get($url)->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_user_does_not_emit_a_second_verified_event(): void
    {
        Event::fake([Verified::class]);
        $verifiedAt = now()->subDay()->startOfSecond();
        $user = User::factory()->create(['email_verified_at' => $verifiedAt]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk()->assertJsonPath('message', 'Email already verified.');
        $this->assertSame($verifiedAt->timestamp, $user->fresh()->email_verified_at->timestamp);
        Event::assertNotDispatched(Verified::class);
    }

    public function test_user_active_workspace_is_resolved_through_membership(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $this->assertNotNull($user->activeWorkspace());
        $this->assertSame($workspace->id, $user->activeWorkspace()->id);
        $this->assertSame('owner', $user->activeWorkspaceMembership()->role);
    }
}
