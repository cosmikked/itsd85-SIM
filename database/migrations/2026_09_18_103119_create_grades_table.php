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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            
            // Midterm
            $table->decimal('midterm_raw_score', 5, 2)->nullable();
            $table->decimal('midterm_equivalent_grade', 3, 2)->nullable();
            
            // Finalterm
            $table->decimal('finalterm_raw_score', 5, 2)->nullable();
            $table->decimal('finalterm_equivalent_grade', 3, 2)->nullable(); 
            
            // Computed Final
            $table->decimal('final_raw_score', 5, 2)->nullable();
            $table->decimal('final_equivalent_grade', 3, 2)->nullable();
            
            // Re-exam
            $table->decimal('re_exam_raw_score', 5, 2)->nullable();
            $table->decimal('re_exam_equivalent_grade', 3, 2)->nullable();

            // Unified Remarks/Status (e.g. PASSED, FAILED, CONDITIONAL, INCOMPLETE, DROPPED)
            $table->string('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
