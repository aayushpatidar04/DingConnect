<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_retailer_wallet_and_sanctum_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'API Retailer',
            'email' => 'retailer@example.test',
            'phone' => '447700900111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'shop_name' => 'Test Shop',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'retailer')
            ->assertJsonPath('data.user.kyc_status', 'pending')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('users', [
            'email' => 'retailer@example.test',
            'role' => 'retailer',
            'kyc_status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallets', ['user_id' => $response->json('data.user.id')]);
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $response->json('data.user.id')]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_valid_otp_creates_a_bearer_token(): void
    {
        $user = User::factory()->create(['role' => 'retailer', 'is_active' => true]);
        $user->forceFill([
            'login_otp_hash' => Hash::make('123456'),
            'login_otp_expires_at' => now()->addMinutes(5),
            'login_otp_attempts' => 0,
        ])->save();

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'mobile-app',
        ]);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id]);
    }

    public function test_login_requires_email_otp_before_creating_a_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'retailer', 'password' => 'correct-password']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.requires_otp', true)
            ->assertJsonMissingPath('data.access_token');

        Notification::assertSentOnDemandOnce(LoginOtpNotification::class);
        $this->assertNotNull($user->fresh()->login_otp_hash);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_login_uses_api_middleware_without_web_csrf(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/auth/login', 'POST'));
        $middleware = $route->gatherMiddleware();

        $this->assertContains('api', $middleware);
        $this->assertNotContains('web', $middleware);
    }

    public function test_invalid_login_credentials_return_401(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_sanctum_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('mobile-app');
        $otherToken = $user->createToken('tablet');

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_deactivated_accounts_are_blocked_but_can_revoke_their_token(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_retailer_profile_update_does_not_allow_role_escalation(): void
    {
        $user = User::factory()->create([
            'role' => 'retailer',
            'name' => 'Original Name',
            'is_active' => true,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'retailer');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'retailer',
        ]);
    }
}
