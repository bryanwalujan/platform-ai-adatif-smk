<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `pbl_projects.topic_id` sudah nullable (lihat
     * 2026_06_06_031836_add_columns_to_pbl_projects_table), jadi subject
     * tidak selalu bisa diturunkan lewat topic — perlu kolom langsung di
     * sini. Nullable dulu, backfill di migration terpisah.
     */
    public function up(): void
    {
        Schema::table('pbl_projects', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('topic_id')
                  ->constrained('subjects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pbl_projects', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });
    }
};
