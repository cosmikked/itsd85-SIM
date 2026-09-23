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
        // Fetch random enrollments regardless of status
        $enrollments = Enrollment::inRandomOrder()
            ->limit(150)
            ->get();

        foreach ($enrollments as $index => $enrollment) {
            if ($enrollment->status === 'dropped') {
                // Dropped enrollments get a DROPPED grade record
                Grade::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'remarks' => 'DROPPED',
                ]);

                continue;
            }

            if ($index % 5 === 0) {
                // Every 5th grade is incomplete — no scores, INCOMPLETE remark
                Grade::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'remarks' => 'INCOMPLETE',
                ]);

                continue;
            }

            $midtermRawScore = fake()->randomFloat(2, 50, 100);
            $midtermScale = $this->matchingGradeScale($midtermRawScore);

            $finaltermRawScore = fake()->randomFloat(2, 50, 100);
            $finaltermScale = $this->matchingGradeScale($finaltermRawScore);

            $finalRawScore = (1 / 3 * $midtermRawScore) + (2 / 3 * $finaltermRawScore);
            $finalScale = $this->matchingGradeScale($finalRawScore);

            $remarks = ($finalScale?->grade_point <= 3.00) ? 'PASSED' : 'FAILED';
            if ($finalRawScore >= 30.00 && $finalRawScore <= 49.99) {
                $remarks = 'CONDITIONAL';
            }

            Grade::factory()->create([
                'enrollment_id' => $enrollment->id,
                'midterm_raw_score' => $midtermRawScore,
                'midterm_equivalent_grade' => $midtermScale?->grade_point,
                'finalterm_raw_score' => $finaltermRawScore,
                'finalterm_equivalent_grade' => $finaltermScale?->grade_point,
                'final_raw_score' => $finalRawScore,
                'final_equivalent_grade' => $finalScale?->grade_point,
                'remarks' => $remarks,
            ]);
        }
    }

    /**
     * Look up the grade_scales band a raw score actually falls into.
     */
    private function matchingGradeScale(float $rawScore): ?GradeScale
    {
        return GradeScale::where('min_score', '<=', $rawScore)
            ->where('max_score', '>=', $rawScore)
            ->first();
    }
}
