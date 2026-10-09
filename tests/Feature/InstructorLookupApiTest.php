<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_lookup_requires_authentication(): void
    {
        $this->getJson('/api/v1/instructors')->assertUnauthorized();
    }

    public function test_administrator_and_registrar_only_get_instructors_with_safe_fields(): void
    {
        $instructor = User::factory()->instructor()->create(['name' => 'Ada Teacher']);
        User::factory()->student()->create();
        User::factory()->registrar()->create();

        foreach ([User::factory()->administrator()->create(), User::factory()->registrar()->create()] as $caller) {
            Sanctum::actingAs($caller);

            $response = $this->getJson('/api/v1/instructors');

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Instructors retrieved successfully.')
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $instructor->id)
                ->assertJsonPath('data.0.name', 'Ada Teacher');
            $this->assertSame(['id', 'name', 'email', 'status'], array_keys($response->json('data.0')));
        }
    }

    public function test_instructors_can_be_searched_filtered_and_paginated(): void
    {
        Sanctum::actingAs(User::factory()->registrar()->create());
        User::factory()->instructor()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        User::factory()->instructor()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com', 'status' => 'inactive']);

        $this->getJson('/api/v1/instructors?search=alice')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/instructors?search=jones')->assertOk()->assertJsonPath('data.0.name', 'Bob Jones');
        $this->getJson('/api/v1/instructors?status=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/instructors?status=inactive')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/instructors?sort=-name&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bob Jones')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_instructors_and_students_cannot_use_the_lookup(): void
    {
        $student = Student::factory()->create();

        foreach ([User::factory()->instructor()->create(), User::find($student->user_id)] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/instructors')->assertForbidden();
        }
    }

    public function test_registrar_still_cannot_manage_users(): void
    {
        Sanctum::actingAs(User::factory()->registrar()->create());

        $this->getJson('/api/v1/users')->assertForbidden();
    }
}
