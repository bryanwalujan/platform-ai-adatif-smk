<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_logs.quiz_score` dipakai oleh `AdaptiveEngineService::updateMastery()`
     * (dipanggil setiap submit kuis) dan model `LearningLog` sejak awal, TAPI
     * kolom ini tidak pernah tercatat di migration manapun di git — ditemukan
     * saat testing Fase 3 (INSERT gagal dengan "no such column" di SQLite
     * bersih dari migration git).
     *
     * Kemungkinan besar ini schema drift yang sama seperti `users.photo_path`
     * (pernah ditambah manual di produksi, migration-nya tidak pernah dibuat)
     * — makanya pakai Schema::hasColumn() guard supaya migration ini AMAN
     * dijalankan baik di DB yang sudah punya kolom ini (drift) maupun yang
     * belum (kalau ternyata memang belum ada, artinya submit kuis di
     * produksi selama ini error 500 — migration ini sekalian jadi perbaikannya).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('learning_logs', 'quiz_score')) {
            Schema::table('learning_logs', function (Blueprint $table) {
                $table->float('quiz_score')->nullable()->after('topic_id');
            });
        }
    }

    /**
     * Sengaja no-op — kalau kolom ini ternyata sudah ada sebelum migration ini
     * (kasus drift), kita tidak mau accidentally drop kolom yang sudah dipakai
     * data produksi asli saat rollback.
     */
    public function down(): void
    {
        //
    }
};
