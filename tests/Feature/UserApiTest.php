<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'New Instructor',
            'email' => 'new.instructor@example.com',
            'password' => 'secret-password',
            'role' => 'instructor',
            'status' => 'active',
        ];
    }

    public function test_users_require_authentication(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users', $this->validPayload())->assertUnauthorized();
        $this->getJson("/api/v1/users/{$user->id}")->assertUnauthorized();
        $this->patchJson("/api/v1/users/{$user->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/v1/users/{$user->id}")->assertUnauthorized();
    }

    public function test_administrator_can_list_and_show_users_without_password_fields(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $user = User::factory()->instructor()->create();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Users retrieved successfully.');
        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_administrator_can_create_a_user_with_a_hashed_password(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->postJson('/api/v1/users', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.role', 'instructor')
            ->assertJsonMissingPath('data.password');

        $created = User::where('email', 'new.instructor@example.com')->firstOrFail();
        $this->assertNotSame('secret-password', $created->password);
        $this->assertTrue(password_verify('secret-password', $created->password));
    }

    public function test_create_validates_input(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());

        $this->postJson('/api/v1/users', ['role' => 'janitor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'status']);
    }

    public function test_administrator_can_update_and_deactivate_a_user(): void
    {
        Sanctum::actingAs(User::factory()->administrator()->create());
        $user = User::factory()->instructor()->create(['name' => 'Old Name']);

        $this->patchJson("/api/v1/users/{$user->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();
        $this->assertSame('inactive', $user->fresh()->status);
    }

    public function test_every_non_administrator_role_is_forbidden(): void
    {
        $target = User::factory()->create();
        $student = Student::factory()->create();

        $callers = [
            User::factory()->registrar()->create(),
            User::factory()->instructor()->create(),
            User::find($student->user_id),
        ];

        foreach ($callers as $caller) {
            Sanctum::actingAs($caller);

            $this->getJson('/api/v1/users')->assertForbidden();
            $this->postJson('/api/v1/users', $this->validPayload())->assertForbidden();
            $this->getJson("/api/v1/users/{$target->id}")->assertForbidden();
            $this->patchJson("/api/v1/users/{$target->id}", ['name' => 'Hacked'])->assertForbidden();
            $this->deleteJson("/api/v1/users/{$target->id}")->assertForbidden();
        }

        $this->assertNotSame('Hacked', $target->fresh()->name);
        $this->assertSame('active', $target->fresh()->status);
    }
}
