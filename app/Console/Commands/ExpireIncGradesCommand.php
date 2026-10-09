<?php

namespace App\Console\Commands;

use App\Models\Grade;
use Illuminate\Console\Command;

class ExpireIncGradesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-inc-grades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expires INC grades that have passed their deadline.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredGrades = Grade::where('is_inc', true)
            ->whereNotNull('inc_expiration_date')
            ->where('inc_expiration_date', '<', today())
            ->get();

        $count = 0;
        foreach ($expiredGrades as $grade) {
            $grade->is_inc = false;
            $grade->final_equivalent_grade = 5.0;
            $grade->remarks = 'Failed';
            // the automatic failure is an official result
            $grade->final_status = 'published';
            $grade->final_published_at = $grade->final_published_at ?? now();
            $grade->save();
            $count++;
        }

        $this->info("Expired {$count} INC grades.");
    }
}
