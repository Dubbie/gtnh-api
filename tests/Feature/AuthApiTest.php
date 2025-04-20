<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminEmail;
    protected string $adminPassword;
    protected User $adminUser;

    /**
     * Set up the admin user based on environment variables before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Let's use environment variables like the seeder for consistency here.
        $this->adminEmail = env('ADMIN_USER_EMAIL', 'admin@example.com');
        $this->adminPassword = env('ADMIN_USER_PASSWORD', 'password');

        // Create the admin user using the factory for a clean state
        $this->adminUser = User::factory()->create([
            'name' => env('ADMIN_USER_NAME', 'Admin User'),
            'email' => $this->adminEmail,
            'password' => Hash::make($this->adminPassword),
        ]);
    }

    // === POST /api/v1/login ===
    public function test_admin_user_can_login_successfully(): void
    {
        $credentials = [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ];

        $response = $this->postJson('/api/v1/login', $credentials);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'attributes' => ['name', 'email', 'created_at', 'updated_at']
                ],
                'token' // Expect a token
            ])
            ->assertJsonPath('data.attributes.email', $this->adminEmail)
            ->assertJsonPath('data.id', $this->adminUser->id);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $credentials = [
            'email' => $this->adminEmail,
            'password' => 'wrong-password',
        ];

        $response = $this->postJson('/api/v1/login', $credentials);

        $response->assertStatus(401)->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_with_non_existent_email(): void
    {
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => $this->adminPassword,
        ];

        $response = $this->postJson('/api/v1/login', $credentials);

        $response->assertStatus(401)->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_with_missing_email(): void
    {
        $credentials = ['password' => $this->adminPassword];
        $response = $this->postJson('/api/v1/login', $credentials);
        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_missing_password(): void
    {
        $credentials = ['email' => $this->adminEmail];
        $response = $this->postJson('/api/v1/login', $credentials);
        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_login_action_deletes_existing_tokens(): void
    {
        // Give the user an initial token
        $this->adminUser->createToken('token_to_delete');
        $this->assertDatabaseCount('personal_access_tokens', 1); // Verify initial token exists

        // Log in again
        $credentials = ['email' => $this->adminEmail, 'password' => $this->adminPassword];
        $this->postJson('/api/v1/login', $credentials)->assertStatus(200);

        // Assert that ALL previous tokens for this user are gone, and only ONE new one exists
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }


    // === POST /api/v1/logout ===
    public function test_authenticated_user_can_logout(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        // Logout using the token
        $response = $this->withToken($token)
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        // Verify token is deleted/invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->adminUser->id,
        ]);
    }

    public function test_logout_action_deletes_current_token(): void
    {
        $tokenInstance = $this->adminUser->createToken('token_for_logout');
        $token = $tokenInstance->plainTextToken;
        $tokenId = $tokenInstance->accessToken->id;

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);

        // Logout using the token
        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertStatus(200);

        // Verify the specific token ID is deleted
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function request_with_invalid_or_deleted_token_fails(): void
    {
        // Use a token string that was never valid or was deleted
        $invalidToken = '1|thisTokenIsInvalidOrDeleted';

        $this->withToken($invalidToken)
            ->getJson('/api/v1/user')
            ->assertStatus(401); // Should be Unauthorized
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    // === GET /api/v1/user ===
    public function test_authenticated_user_can_get_their_details(): void
    {
        $token = $this->adminUser->createToken('test_token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'type', 'attributes']])
            ->assertJsonPath('data.id', $this->adminUser->id)
            ->assertJsonPath('data.attributes.email', $this->adminUser->email);
    }

    public function test_unauthenticated_user_cannot_get_user_details(): void
    {
        $response = $this->getJson('/api/v1/user');
        $response->assertStatus(401);
    }
}
