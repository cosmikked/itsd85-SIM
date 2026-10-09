<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Midterm and final results are published independently. New rows start as
     * drafts; rows that already exist were visible before this workflow
     * existed, so they are backfilled as published.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->enum('midterm_status', ['draft', 'published'])->default('draft');
            $table->timestamp('midterm_published_at')->nullable();

            // covers finalterm, final, re-exam, INC and remarks
            $table->enum('final_status', ['draft', 'published'])->default('draft');
            $table->timestamp('final_published_at')->nullable();
        });

        $now = now();

        DB::table('grades')->update([
            'midterm_status' => 'published',
            'midterm_published_at' => $now,
            'final_status' => 'published',
            'final_published_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn([
                'midterm_status',
                'midterm_published_at',
                'final_status',
                'final_published_at',
            ]);
        });
    }
};
