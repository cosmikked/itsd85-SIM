<?php

namespace Tests\Feature;

use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireIncGradesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_inc_grade_automatically_converted_to_failed_after_deadline(): void
    {
        $grade = Grade::factory()->create([
            'is_inc' => true,
            'inc_expiration_date' => now()->subDay(), // Expired
            'remarks' => 'Incomplete',
        ]);

        $activeGrade = Grade::factory()->create([
            'is_inc' => true,
            'inc_expiration_date' => now()->addDays(5), // Not Expired
            'remarks' => 'Incomplete',
        ]);

        $this->artisan('app:expire-inc-grades')->assertSuccessful();

        $this->assertDatabaseHas('grades', [
            'id' => $grade->id,
            'is_inc' => false,
            'final_equivalent_grade' => 5.0,
            'remarks' => 'Failed',
        ]);

        $this->assertDatabaseHas('grades', [
            'id' => $activeGrade->id,
            'is_inc' => true,
            'remarks' => 'Incomplete',
        ]);
    }
}
