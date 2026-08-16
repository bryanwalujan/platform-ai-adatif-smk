<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RPP bisa dilampiri file (dokumen RPP asli, dsb) sebagai alternatif
     * atau tambahan dari isi manual (learning_objective/description) —
     * kolom sama persis polanya seperti materials.file_path/file_name/file_type.
     */
    public function up(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('is_completed');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_type')->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_type']);
        });
    }
};
