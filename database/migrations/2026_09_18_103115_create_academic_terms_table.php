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
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->enum('term', ['First Semester', 'Second Semester', 'MidYear']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'inactive'])
                ->default('active');
            $table->timestamps();

            $table->unique(['academic_year', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
