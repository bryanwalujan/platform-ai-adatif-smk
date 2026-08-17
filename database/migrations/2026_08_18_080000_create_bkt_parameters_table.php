<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parameter Bayesian Knowledge Tracing (BKT) — dipakai
     * BayesianKnowledgeTracingService untuk memperkirakan probabilitas
     * penguasaan (mastery) siswa per topik berdasarkan riwayat benar/salah
     * kuisnya (bukan rumus tetap seperti AdaptiveEngineService::updateMastery
     * yang sudah ada — parameter di tabel ini justru DIESTIMASI dari data
     * historis lewat `php artisan bkt:fit`, itulah bagian "machine learning"-nya).
     *
     * subject_id NULLABLE dengan makna khusus: baris dengan subject_id NULL
     * adalah parameter GLOBAL (fallback lintas mapel) — dipakai kalau satu
     * mapel belum punya cukup data kuis historis untuk di-fit sendiri.
     * Pola ini konsisten dengan SubjectAccessService::resolveSubjectId di
     * bagian lain aplikasi (fallback bertingkat, bukan hard requirement).
     */
    public function up(): void
    {
        Schema::create('bkt_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->unique()->constrained()->cascadeOnDelete();

            // P(L0): probabilitas siswa SUDAH menguasai topik sebelum ada
            // percobaan kuis sama sekali (prior awal).
            $table->float('p_l0');
            // P(T): probabilitas siswa BERPINDAH dari belum-menguasai ke
            // menguasai setelah satu kesempatan belajar/kuis (learning rate).
            $table->float('p_t');
            // P(S): probabilitas siswa yang SUDAH menguasai tetap menjawab
            // salah (slip — mis. ceroboh).
            $table->float('p_s');
            // P(G): probabilitas siswa yang BELUM menguasai tetap menjawab
            // benar (guess — menebak).
            $table->float('p_g');

            // Metadata hasil fitting — buat transparansi & evaluasi model,
            // bukan dipakai langsung dalam perhitungan mastery.
            $table->unsignedInteger('fitted_from_sequences')->default(0);
            $table->float('log_likelihood')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkt_parameters');
    }
};
