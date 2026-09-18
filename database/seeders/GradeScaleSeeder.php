<?php

namespace Database\Seeders;

use App\Models\GradeScale;
use Illuminate\Database\Seeder;

class GradeScaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // GradeScaleFactory's band table has exactly 11 entries covering
        // 0-100 with no gaps — seed the complete reference table.
        GradeScale::factory()->count(11)->create();
    }
}
