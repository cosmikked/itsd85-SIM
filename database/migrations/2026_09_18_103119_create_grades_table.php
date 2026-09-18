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
            $table->decimal('midterm_raw_score', 5, 2)->nullable();
            $table->foreignId('midterm_grade_scale_id')->nullable()->constrained('grade_scales')->restrictOnDelete();
            $table->decimal('final_raw_score', 5, 2)->nullable();
            $table->foreignId('final_grade_scale_id')->nullable()->constrained('grade_scales')->restrictOnDelete();

            // for cases if student has INCOMPLETE grade 
            // happens when midterm or final grade is null 
            $table->string('status')->nullable();
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
