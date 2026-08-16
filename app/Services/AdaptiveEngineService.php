<?php

namespace App\Services;

use App\Models\InteractionLog;
use App\Models\LearningLog;
use App\Models\StudentTopicMastery;
use App\Models\Topic;

class AdaptiveEngineService
{
    /** Hari bebas-lupa sebelum mastery mulai memudar tanpa aktivitas */
    private const DECAY_GRACE_DAYS = 14;

    /** Poin memudar per hari setelah masa bebas-lupa lewat */
    private const DECAY_PER_DAY = 1.0;

    /** Batas maksimum penurunan akibat lupa, sebagai rasio dari mastery asli */
    private const DECAY_MAX_RATIO = 0.4;

    /** Jumlah maksimum rekomendasi yang ditampilkan sekaligus */
    private const MAX_RECOMMENDATIONS = 5;

    /** Berapa hari tanpa aktivitas sebelum topik mastery-tinggi tetap disarankan diulang */
    private const SPACED_REPETITION_DAYS = 21;

    private const PRIORITY_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * Update mastery siswa setelah mengerjakan kuis.
     * Dipanggil dari QuizController@submit.
     *
     * PERBAIKAN dari versi sebelumnya:
     * - Bonus diberikan jika belajar >= 15 menit (sebelumnya < 15 menit — terbalik)
     * - Bonus waktu belajar & nilai bagus dulu ditambahkan LANGSUNG ke
     *   mastery_level di luar weighted-average, jadi bisa numpuk tanpa batas
     *   tiap kali kuis dikerjakan — mastery cepat mentok 100 walau performa
     *   sebenarnya biasa saja. Sekarang bonus dilebur ke DALAM nilai kuis
     *   efektif (dibatasi maks 100) sebelum masuk weighted-average, jadi
     *   pengaruhnya tetap proporsional dengan bobot 40% nilai baru.
     * - LearningLog dicatat di sini agar tidak perlu duplikasi di QuizController
     */
    public function updateMastery(int $userId, int $topicId, float $quizScore, int $timeSpentMinutes = 0): StudentTopicMastery
    {
        $mastery = StudentTopicMastery::firstOrCreate(
            ['user_id' => $userId, 'topic_id' => $topicId],
            ['mastery_level' => 0, 'attempts' => 0]
        );

        $mastery->attempts += 1;

        $bonus = 0;
        if ($timeSpentMinutes >= 30) $bonus += 4;
        elseif ($timeSpentMinutes >= 15) $bonus += 2;
        if ($quizScore >= 90) $bonus += 4;
        elseif ($quizScore >= 70) $bonus += 2;

        $effectiveScore = min(100, $quizScore + $bonus);

        // Weighted average: 60% nilai lama, 40% nilai kuis baru (efektif)
        $newMastery = ($mastery->mastery_level * 0.6) + ($effectiveScore * 0.4);

        $mastery->mastery_level = min(100, max(0, round($newMastery, 2)));
        $mastery->last_accessed = now();
        $mastery->save();

        // Catat ke learning log
        LearningLog::create([
            'user_id'            => $userId,
            'topic_id'           => $topicId,
            'quiz_score'         => $quizScore,
            'time_spent_minutes' => $timeSpentMinutes,
        ]);

        return $mastery;
    }

    /**
     * TAMBAH: mastery "efektif" saat ini, memperhitungkan efek lupa (forgetting
     * curve) kalau siswa lama tidak menyentuh topik ini. `mastery_level` di
     * database TETAP nilai mentah hasil kuis terakhir (riwayat performa asli,
     * dipakai lagi sebagai basis weighted-average di updateMastery()) — decay
     * hanya diterapkan saat DIBACA, supaya tidak terjadi feedback loop (decay
     * di atas decay tiap kali dihitung ulang). Dipakai oleh rekomendasi, level
     * PBL, dan tampilan mastery ke siswa/guru, supaya semuanya mencerminkan
     * kemampuan siswa SEKARANG, bukan snapshot kuis terakhir yang mungkin
     * sudah lama berlalu.
     */
    public function effectiveMastery(StudentTopicMastery $m): float
    {
        if (!$m->last_accessed) {
            return (float) $m->mastery_level;
        }

        $daysSince = $m->last_accessed->diffInDays(now());
        if ($daysSince <= self::DECAY_GRACE_DAYS) {
            return (float) $m->mastery_level;
        }

        $decayDays = $daysSince - self::DECAY_GRACE_DAYS;
        $decay = min(
            $m->mastery_level * self::DECAY_MAX_RATIO,
            $decayDays * self::DECAY_PER_DAY
        );

        return round(max(0, $m->mastery_level - $decay), 2);
    }

    /**
     * TAMBAH: rata-rata mastery efektif (sudah kena decay) siswa untuk mapel-
     * mapel ini. Dipakai bareng oleh endpoint index/progress-report dan
     * getPBLLevel() supaya angkanya konsisten — dulu masing-masing tempat
     * menghitung rata-rata dengan cara berbeda (satu pakai mastery_level
     * mentah dari query, satu lagi dari service).
     */
    public function getAverageMastery(int $userId, array $subjectIds): float
    {
        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->get();

        if ($masteries->isEmpty()) {
            return 0.0;
        }

        return round($masteries->avg(fn ($m) => $this->effectiveMastery($m)), 2);
    }

    /**
     * Buat daftar rekomendasi personal untuk siswa.
     * Struktur response dipakai oleh RecommendationScreen Flutter.
     *
     * $subjectIds: mata pelajaran mana saja yang relevan (biasanya semua
     * mapel yang diikuti siswa, atau satu mapel spesifik). WAJIB diisi
     * pemanggil (bukan nullable) — supaya rekomendasi topik baru tidak
     * pernah menyarankan topik dari mapel yang tidak diikuti siswa.
     */
    public function getRecommendations(int $userId, array $subjectIds): array
    {
        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->get();

        // Ambil data materi yang sering diulang sebagai sinyal kesulitan
        $repeatedMaterials = InteractionLog::where('user_id', $userId)
            ->where('action', 'repeat_material')
            ->where('open_count', '>=', 3) // diulang 3x+ = kemungkinan kesulitan
            ->with('topic:id,title')
            ->get()
            ->groupBy('topic_id');

        $recommendations = [];

        foreach ($masteries as $m) {
            if (!$m->topic) continue;

            $topicId   = $m->topic->id;
            // PERBAIKAN: pakai mastery EFEKTIF (kena decay), bukan mastery_level
            // mentah — topik yang dulu dikuasai tapi lama tidak disentuh sekarang
            // ikut ditandai butuh perhatian, bukan dianggap tetap aman selamanya.
            $effective = $this->effectiveMastery($m);
            $hasRepeatedMaterial = $repeatedMaterials->has($topicId);

            if ($effective < 45) {
                $recommendations[] = [
                    'type'     => 'review',
                    'priority' => 'high',
                    'message'  => 'Kamu sangat perlu mengulang topik ini',
                    'topic'    => ['id' => $topicId, 'title' => $m->topic->title],
                ];
            } elseif ($effective < 75) {
                $recommendations[] = [
                    'type'     => 'practice',
                    'priority' => 'medium',
                    'message'  => 'Latihan lebih banyak di topik ini akan sangat membantu',
                    'topic'    => ['id' => $topicId, 'title' => $m->topic->title],
                ];
            } elseif ($hasRepeatedMaterial) {
                // Mastery tinggi tapi ada materi yang sering diulang
                // Kemungkinan siswa mengulang untuk memperkuat pemahaman
                $recommendations[] = [
                    'type'     => 'review',
                    'priority' => 'medium',
                    'message'  => 'Kamu sering membuka ulang materi ini — coba kerjakan kuis untuk mengukur pemahamanmu',
                    'topic'    => ['id' => $topicId, 'title' => $m->topic->title],
                ];
            } elseif ($m->last_accessed && $m->last_accessed->diffInDays(now()) >= self::SPACED_REPETITION_DAYS) {
                // TAMBAH: spaced repetition — mastery tercatat tinggi, tapi sudah
                // lama tidak disentuh. Diingatkan sebelum keburu turun, bukan
                // sesudah kelihatan turun di mastery_level.
                $daysSince = $m->last_accessed->diffInDays(now());
                $recommendations[] = [
                    'type'     => 'refresh',
                    'priority' => 'low',
                    'message'  => "Sudah {$daysSince} hari sejak terakhir kamu belajar topik ini — coba refresh ingatanmu",
                    'topic'    => ['id' => $topicId, 'title' => $m->topic->title],
                ];
            }
        }

        if (count($recommendations) < 2) {
            // PERBAIKAN: urut sesuai urutan kurikulum (topics.order) — dulu
            // ambil topik pertama tanpa urutan eksplisit, bisa melompat dari
            // urutan belajar yang seharusnya (mis. langsung ke topik lanjut
            // sebelum topik dasarnya).
            $nextTopic = Topic::whereIn('subject_id', $subjectIds)
                ->whereDoesntHave('studentMasteries', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->orderBy('order')
                ->first();

            if ($nextTopic) {
                $recommendations[] = [
                    'type'     => 'new',
                    'priority' => 'high',
                    'message'  => 'Topik baru yang cocok untuk kamu pelajari selanjutnya',
                    'topic'    => ['id' => $nextTopic->id, 'title' => $nextTopic->title],
                ];
            }
        }

        // PERBAIKAN: urutkan berdasar prioritas (high dulu) lalu batasi
        // jumlahnya — dulu tidak diurutkan & tidak dibatasi, siswa dengan
        // banyak topik lemah bisa kebanjiran rekomendasi sekaligus tanpa tahu
        // mana yang paling mendesak dikerjakan lebih dulu.
        usort(
            $recommendations,
            fn ($a, $b) => self::PRIORITY_RANK[$a['priority']] <=> self::PRIORITY_RANK[$b['priority']]
        );

        return array_slice($recommendations, 0, self::MAX_RECOMMENDATIONS);
    }

    /**
     * Tentukan level proyek PBL berdasarkan mastery siswa.
     * $subjectIds sama seperti getRecommendations() — wajib diisi.
     *
     * PERBAIKAN: dulu murni dari rata-rata mastery, jadi siswa yang baru
     * mencoba 1 topik dengan nilai bagus langsung dilabel "Lanjutan" —
     * tidak representatif untuk seluruh mapel. Sekarang level lanjut/menengah
     * juga mensyaratkan cakupan topik yang sudah dicoba (coverage), dan
     * pakai mastery EFEKTIF (kena decay) supaya tidak overclaim kemampuan
     * yang sebetulnya sudah lama tidak diasah.
     */
    public function getPBLLevel(int $userId, array $subjectIds): string
    {
        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->get();

        if ($masteries->isEmpty()) {
            return 'Dasar';
        }

        $avgEffective = $masteries->avg(fn ($m) => $this->effectiveMastery($m));

        $totalTopics = Topic::whereIn('subject_id', $subjectIds)->count();
        $coverage    = $totalTopics > 0 ? $masteries->count() / $totalTopics : 0;

        if ($avgEffective >= 85 && $coverage >= 0.6) return 'Lanjutan';
        if ($avgEffective >= 65 && $coverage >= 0.3) return 'Menengah';
        return 'Dasar';
    }
}
