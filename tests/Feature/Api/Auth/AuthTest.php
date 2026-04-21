<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void {

        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token', 'user'
        ]);
    }

    public function test_user_cannot_login_with_incorrect_credentials(): void {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'ljlkjlkjlkj',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('email');
    }

    public function test_user_can_register_and_receive_token() : void {
        $payload = [
            'name' => 'John Doe',
            'email' => $email = 'john@doe.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertCreated();
        $response->assertJsonStructure(['user', 'auth_token']);
        $this->assertDatabaseHas('users', [
            'email' => $email
        ]);
    }

    public function test_user_cannot_register_with_invalid_data() : void {
        $payload = [
            'name' => '',
            'email' => 'john-doe',
            'password' => 'short',
            'password_confirmation' => 'password1234',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_logout() : void {
        $user = User::factory()->create();

        $token = $user->createToken('auth_token')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/auth/logout', []);

        $response->assertNoContent();

        $this->app['auth']->forgetGuards();

        $protected = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/user');

        $protected->assertStatus(401);
    }

    public function test_guest_cannot_access_user_endpoint() : void {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_user_endpoint() : void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/user');
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
