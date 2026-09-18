<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeScale;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // A dropped enrollment shouldn't have a grade record at all.
        $enrollmentIds = Enrollment::whereIn('status', ['enrolled', 'completed'])
            ->inRandomOrder()
            ->limit(150)
            ->pluck('id');

        foreach ($enrollmentIds as $index => $enrollmentId) {
            if ($index % 5 === 0) {
                // Every 5th grade is incomplete — no score, no grade-scale link yet.
                Grade::factory()->create([
                    'enrollment_id' => $enrollmentId,
                    'status' => 'INCOMPLETE',
                ]);

                continue;
            }

            $midtermRawScore = fake()->randomFloat(2, 50, 100);
            $finalRawScore = fake()->randomFloat(2, 50, 100);

            Grade::factory()->create([
                'enrollment_id' => $enrollmentId,
                'midterm_raw_score' => $midtermRawScore,
                'midterm_grade_scale_id' => $this->matchingGradeScaleId($midtermRawScore),
                'final_raw_score' => $finalRawScore,
                'final_grade_scale_id' => $this->matchingGradeScaleId($finalRawScore),
            ]);
        }
    }

    /**
     * Look up the grade_scales band a raw score actually falls into, the
     * same way the real grade-submission endpoint will — never picked
     * independently of the score itself.
     */
    private function matchingGradeScaleId(float $rawScore): int
    {
        return GradeScale::where('min_score', '<=', $rawScore)
            ->where('max_score', '>=', $rawScore)
            ->value('id');
    }
}
