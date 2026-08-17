<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TEMUAN saat membangun BayesianKnowledgeTracingService: tabel
     * test_results ternyata HANYA menyimpan pre_test/post_test (dibatasi
     * unique(user_id, topic_id, type), satu baris per jenis per topik).
     * Percobaan kuis REGULER (yang paling sering dikerjakan siswa, dan
     * paling sering diulang — sinyal paling kaya untuk melacak proses
     * belajar) TIDAK PERNAH dicatat per-percobaan di mana pun; skornya
     * cuma lewat sekilas ke AdaptiveEngineService::updateMastery() lalu
     * hilang (yang tersimpan cuma nilai mastery_level TERBARU, bukan
     * riwayatnya).
     *
     * BKT butuh URUTAN observasi benar/salah dari waktu ke waktu untuk
     * melacak proses belajar seorang siswa pada satu topik — itu sebabnya
     * tabel log baru ini dibuat, alih-alih memakai test_results yang
     * datanya terlalu sedikit (maksimal 2 baris per siswa per topik).
     *
     * Sepenuhnya aditif — tidak mengubah tabel/perilaku yang sudah ada.
     * Konsekuensi jujur yang diakui di proposal: data historis SEBELUM
     * migration ini tidak bisa direkonstruksi (cold start), jadi fitting
     * BKT baru benar-benar layak jalan setelah cukup banyak percobaan
     * kuis BARU tercatat lewat tabel ini.
     */
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('quiz_type', 20); // salinan quizzes.type saat itu (regular/pre_test/post_test)
            $table->decimal('score', 5, 2);
            $table->boolean('passed'); // score >= passing_score saat itu — sama seperti QuizController::submit()
            $table->timestamps();

            $table->index(['user_id', 'topic_id', 'created_at'], 'quiz_attempts_sequence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
