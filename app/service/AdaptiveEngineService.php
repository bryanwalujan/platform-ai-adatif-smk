<?php

namespace App\Services;

use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\LearningLog;

class AdaptiveEngineService
{
    public function updateMastery($userId, $topicId, $quizScore, $timeSpentMinutes = 0)
    {
        $mastery = StudentTopicMastery::firstOrCreate(
            ['user_id' => $userId, 'topic_id' => $topicId],
            ['mastery_level' => 0, 'attempts' => 0]
        );

        $mastery->attempts += 1;

        // Logika Adaptive AI (bisa dikembangkan lebih kompleks)
        $newMastery = ($mastery->mastery_level * 0.6) + ($quizScore * 0.4);

        if ($timeSpentMinutes < 15) $newMastery += 8;      // Bonus cepat
        if ($quizScore >= 90) $newMastery += 7;            // Bonus nilai tinggi

        $mastery->mastery_level = min(100, max(0, round($newMastery, 2)));
        $mastery->last_accessed = now();
        $mastery->save();

        return $mastery;
    }

    public function getRecommendations($userId)
    {
        $masteries = StudentTopicMastery::where('user_id', $userId)
                    ->with('topic')
                    ->orderBy('mastery_level')
                    ->get();

        $recommendations = [];

        foreach ($masteries as $m) {
            if ($m->mastery_level < 45) {
                $recommendations[] = [
                    'type' => 'review',
                    'topic' => $m->topic,
                    'message' => "Kamu sangat perlu mengulang topik ini",
                    'priority' => 'high'
                ];
            } elseif ($m->mastery_level < 75) {
                $recommendations[] = [
                    'type' => 'practice',
                    'topic' => $m->topic,
                    'message' => "Latihan lebih banyak di topik ini akan sangat membantu",
                    'priority' => 'medium'
                ];
            }
        }

        // Rekomendasi topik baru
        if (count($recommendations) < 2) {
            $nextTopic = Topic::whereDoesntHave('studentMasteries', function($q) use ($userId) {
                $q->where('user_id', $userId);
            })->first();

            if ($nextTopic) {
                $recommendations[] = [
                    'type' => 'new',
                    'topic' => $nextTopic,
                    'message' => "Topik baru yang cocok untuk kamu",
                    'priority' => 'high'
                ];
            }
        }

        return $recommendations;
    }

    public function getPBLLevel($userId)
    {
        $avgMastery = StudentTopicMastery::where('user_id', $userId)->avg('mastery_level') ?? 0;

        if ($avgMastery >= 85) return 'Lanjutan';
        if ($avgMastery >= 65) return 'Menengah';
        return 'Dasar';
    }
}