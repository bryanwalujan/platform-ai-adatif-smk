<?php

namespace App\Services;

use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\LearningLog;

class AdaptiveEngineService
{
    /**
     * Update mastery siswa setelah mengerjakan kuis.
     * Dipanggil dari QuizController@submit.
     *
     * PERBAIKAN dari versi sebelumnya:
     * - Bonus diberikan jika belajar >= 15 menit (sebelumnya < 15 menit — terbalik)
     * - Ditambah bonus bertingkat untuk nilai sedang (>= 70)
     * - LearningLog dicatat di sini agar tidak perlu duplikasi di QuizController
     */
    public function updateMastery(int $userId, int $topicId, float $quizScore, int $timeSpentMinutes = 0): StudentTopicMastery
    {
        $mastery = StudentTopicMastery::firstOrCreate(
            ['user_id' => $userId, 'topic_id' => $topicId],
            ['mastery_level' => 0, 'attempts' => 0]
        );

        $mastery->attempts += 1;

        // Weighted average: 60% nilai lama, 40% nilai kuis baru
        $newMastery = ($mastery->mastery_level * 0.6) + ($quizScore * 0.4);

        // PERBAIKAN: bonus jika belajar cukup lama (sebelumnya terbalik < 15)
        if ($timeSpentMinutes >= 15) $newMastery += 5;
        if ($timeSpentMinutes >= 30) $newMastery += 3; // bonus ekstra sesi panjang

        // Bonus nilai kuis
        if ($quizScore >= 90) $newMastery += 7;
        elseif ($quizScore >= 70) $newMastery += 3;

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
     * Buat daftar rekomendasi personal untuk siswa.
     * Struktur response dipakai oleh RecommendationScreen Flutter.
     */
    public function getRecommendations(int $userId): array
    {
        $masteries = StudentTopicMastery::where('user_id', $userId)
            ->with('topic:id,title')
            ->orderBy('mastery_level')
            ->get();

        $recommendations = [];

        foreach ($masteries as $m) {
            if (! $m->topic) continue; // skip jika topik dihapus

            if ($m->mastery_level < 45) {
                $recommendations[] = [
                    'type'     => 'review',
                    'priority' => 'high',
                    'message'  => 'Kamu sangat perlu mengulang topik ini',
                    'topic'    => ['id' => $m->topic->id, 'title' => $m->topic->title],
                ];
            } elseif ($m->mastery_level < 75) {
                $recommendations[] = [
                    'type'     => 'practice',
                    'priority' => 'medium',
                    'message'  => 'Latihan lebih banyak di topik ini akan sangat membantu',
                    'topic'    => ['id' => $m->topic->id, 'title' => $m->topic->title],
                ];
            }
        }

        // Sarankan topik baru jika rekomendasi review/practice kurang dari 2
        if (count($recommendations) < 2) {
            $nextTopic = Topic::whereDoesntHave('studentMasteries', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->first();

            if ($nextTopic) {
                $recommendations[] = [
                    'type'     => 'new',
                    'priority' => 'high',
                    'message'  => 'Topik baru yang cocok untuk kamu pelajari selanjutnya',
                    'topic'    => ['id' => $nextTopic->id, 'title' => $nextTopic->title],
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Tentukan level proyek PBL berdasarkan rata-rata mastery.
     */
    public function getPBLLevel(int $userId): string
    {
        $avgMastery = StudentTopicMastery::where('user_id', $userId)
            ->avg('mastery_level') ?? 0;

        if ($avgMastery >= 85) return 'Lanjutan';
        if ($avgMastery >= 65) return 'Menengah';
        return 'Dasar';
    }
}