<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    // ---- PATCH /auth/me -------------------------------------------------

    public function test_update_profile_without_a_token_returns_401(): void
    {
        $this->patchJson('/api/v1/auth/me', ['name' => 'New Name'])->assertUnauthorized();
    }

    public function test_update_profile_changes_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_update_profile_resending_own_email_returns_200(): void
    {
        $user = User::factory()->create();
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', ['email' => $user->email])->assertOk();
    }

    public function test_update_profile_with_an_email_used_by_another_user_returns_422(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', ['email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_update_profile_with_an_invalid_email_returns_422(): void
    {
        $this->actingAsToken(User::factory()->create());

        $this->patchJson('/api/v1/auth/me', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_profile_ignores_role_and_status(): void
    {
        $user = User::factory()->student()->create();
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', [
            'name' => 'Sneaky',
            'role' => 'administrator',
            'status' => 'inactive',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('student', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame('Sneaky', $user->name);
    }

    public function test_update_profile_email_is_synced_to_the_linked_student_record(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', ['email' => 'new.address@example.com'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new.address@example.com']);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'email' => 'new.address@example.com']);
    }

    public function test_update_profile_with_an_email_used_by_another_student_returns_422(): void
    {
        $otherStudent = Student::factory()->create();
        $user = User::factory()->student()->create();
        Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
        $this->actingAsToken($user);

        $this->patchJson('/api/v1/auth/me', ['email' => $otherStudent->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    // ---- POST /auth/forgot-password -------------------------------------

    public function test_forgot_password_for_a_known_email_sends_a_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'If that email is registered, a reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_for_an_unknown_email_returns_the_same_response_and_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertCount(1);
    }

    public function test_forgot_password_with_a_missing_or_invalid_email_returns_422(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ---- POST /auth/reset-password --------------------------------------

    public function test_reset_password_with_a_valid_token_changes_the_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($user, $token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password has been reset.');

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password-123'])
            ->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'old-password'])
            ->assertUnprocessable();
    }

    public function test_reset_password_revokes_existing_api_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('stolen-token');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($user, $token))->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_reset_password_with_a_wrong_token_returns_422_and_keeps_the_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($user, 'wrong-token'))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_reset_password_with_an_invalid_password_returns_422(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            ...$this->resetPayload($user, $token),
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->postJson('/api/v1/auth/reset-password', [
            ...$this->resetPayload($user, $token),
            'password_confirmation' => 'does-not-match',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_token_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($user, $token))->assertOk();

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($user, $token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ---- helpers --------------------------------------------------------

    /** Authenticate subsequent requests with a real Sanctum token for the user. */
    private function actingAsToken(User $user): void
    {
        $this->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken);
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(User $user, string $token): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];
    }
}
