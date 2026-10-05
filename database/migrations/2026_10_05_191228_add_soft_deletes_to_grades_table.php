<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            if (! Schema::hasColumn('grades', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('grades', 'is_inc')) {
                $table->boolean('is_inc')->default(false);
                $table->date('inc_expiration_date')->nullable();
            }
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_terms', 'midterm_grading_deadline')) {
                $table->dateTime('midterm_grading_deadline')->nullable();
                $table->dateTime('final_grading_deadline')->nullable();
                $table->date('inc_completion_deadline')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_inc', 'inc_expiration_date']);
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn(['midterm_grading_deadline', 'final_grading_deadline', 'inc_completion_deadline']);
        });
    }
};
