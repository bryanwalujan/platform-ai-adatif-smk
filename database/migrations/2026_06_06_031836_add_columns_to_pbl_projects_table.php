<?php
// database/migrations/2024_01_06_000000_add_columns_to_pbl_projects_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pbl_projects', function (Blueprint $table) {
            // Relasi topik
            $table->foreignId('topic_id')->nullable()
                  ->constrained()->onDelete('set null')
                  ->after('user_id');

            // File upload
            $table->string('file_path')->nullable()->after('level');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_type')->nullable()->after('file_name');

            // Rubrik penilaian terstruktur (disimpan sebagai JSON)
            // Contoh: {"kreativitas": 85, "teknis": 90, "presentasi": 80}
            $table->json('rubric_scores')->nullable()->after('score');

            // Catatan tambahan dari guru per kriteria
            $table->json('rubric_feedback')->nullable()->after('rubric_scores');

            // Tanggal dinilai
            $table->timestamp('graded_at')->nullable()->after('rubric_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('pbl_projects', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
            $table->dropColumn([
                'topic_id', 'file_path', 'file_name', 'file_type',
                'rubric_scores', 'rubric_feedback', 'graded_at',
            ]);
        });
    }
};