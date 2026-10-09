<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_require_authentication(): void
    {
        $room = Room::factory()->create();

        $this->getJson('/api/v1/rooms')->assertUnauthorized();
        $this->getJson("/api/v1/rooms/{$room->id}")->assertUnauthorized();
    }

    public function test_administrator_and_registrar_can_list_rooms(): void
    {
        Room::factory()->create(['code' => 'RM-101', 'building' => 'Science Hall', 'capacity' => 40]);

        foreach ([User::factory()->administrator()->create(), User::factory()->registrar()->create()] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/rooms')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Rooms retrieved successfully.')
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.code', 'RM-101')
                ->assertJsonPath('data.0.building', 'Science Hall')
                ->assertJsonPath('data.0.capacity', 40)
                ->assertJsonStructure(['data' => [['id', 'code', 'building', 'capacity']]]);
        }
    }

    public function test_rooms_are_paginated(): void
    {
        Sanctum::actingAs(User::factory()->registrar()->create());
        Room::factory()->count(16)->create();

        $this->getJson('/api/v1/rooms')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 16);
        $this->getJson('/api/v1/rooms?per_page=5&page=2')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_rooms_can_be_searched_filtered_and_sorted(): void
    {
        Sanctum::actingAs(User::factory()->registrar()->create());
        Room::factory()->create(['code' => 'LAB-1', 'building' => 'Science Hall']);
        Room::factory()->create(['code' => 'ART-2', 'building' => 'Arts Bldg']);
        Room::factory()->create(['code' => 'LAB-2', 'building' => 'Science Hall']);

        $this->getJson('/api/v1/rooms?search=LAB')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/rooms?search=Arts')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/rooms?building=Science Hall')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/rooms?sort=-code')->assertOk()->assertJsonPath('data.0.code', 'LAB-2');
    }

    public function test_show_returns_the_room_or_404(): void
    {
        Sanctum::actingAs(User::factory()->registrar()->create());
        $room = Room::factory()->create();

        $this->getJson("/api/v1/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $room->id);
        $this->getJson('/api/v1/rooms/999999')->assertNotFound();
    }

    public function test_instructors_and_students_cannot_see_rooms(): void
    {
        $room = Room::factory()->create();
        $student = Student::factory()->create();

        foreach ([User::factory()->instructor()->create(), User::find($student->user_id)] as $user) {
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/rooms')->assertForbidden();
            $this->getJson("/api/v1/rooms/{$room->id}")->assertForbidden();
        }
    }
}
