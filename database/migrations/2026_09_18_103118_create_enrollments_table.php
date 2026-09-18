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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->date('enrollment_date');
            $table->enum('status', ['enrolled', 'dropped', 'completed'])
                ->default('enrolled');
            $table->timestamps();

            // student can only enroll once in a course offering
            $table->unique(['student_id', 'course_offering_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
