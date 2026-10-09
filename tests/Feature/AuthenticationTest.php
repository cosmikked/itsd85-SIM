<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_with_valid_credentials_returns_200_and_a_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure(['data' => ['token']]);
        $this->assertIsString($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_with_wrong_password_returns_422_and_no_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['email']);
        $response->assertJsonMissingPath('data.token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unknown_email_returns_the_same_error_as_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmail->assertUnprocessable();
        $this->assertSame($wrongPassword->json('errors.email'), $unknownEmail->json('errors.email'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_for_an_inactive_user_returns_the_same_generic_error(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
            'status' => 'inactive',
        ]);
        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
        $this->assertSame($wrongPassword->json('errors.email'), $response->json('errors.email'));
        $response->assertJsonMissingPath('data.token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_without_credentials_returns_422(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_with_valid_token_returns_200_and_the_user_without_password(): void
    {
        $user = User::factory()->administrator()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Current user retrieved successfully.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'administrator');
        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.remember_token');
    }

    public function test_logout_revokes_the_token_and_reusing_it_returns_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The guard caches the resolved user for the life of the test app, so the second request would still be authenticated.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_includes_the_student_id_for_a_student_account(): void
    {
        $student = Student::factory()->create();
        $token = User::find($student->user_id)->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.student_id', $student->id);
    }

    public function test_me_has_a_null_student_id_for_a_student_account_without_a_student_record(): void
    {
        $token = User::factory()->student()->create()->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.student_id', null)
            ->assertJsonStructure(['data' => ['student_id']]);
    }

    public function test_me_has_no_student_id_for_staff_and_instructors(): void
    {
        foreach ([User::factory()->administrator()->create(), User::factory()->registrar()->create(), User::factory()->instructor()->create()] as $user) {
            $token = $user->createToken('test-token')->plainTextToken;

            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/auth/me')
                ->assertOk()
                ->assertJsonMissingPath('data.student_id');
        }
    }

    public function test_updating_the_profile_of_a_student_also_returns_the_student_id(): void
    {
        $student = Student::factory()->create();
        $token = User::find($student->user_id)->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/auth/me', ['name' => 'Renamed Student'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Student')
            ->assertJsonPath('data.student_id', $student->id);
    }
}
