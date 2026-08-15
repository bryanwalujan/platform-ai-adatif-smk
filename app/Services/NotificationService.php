<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\StudentTopicMastery;
use App\Models\InteractionLog;
use App\Models\LearningLog;
use App\Models\PblProject;

class NotificationService
{
    /**
     * Generate notifikasi otomatis berdasarkan data AI.
     * Dipanggil saat siswa buka halaman notifikasi.
     * Menggunakan flag 'data' untuk cegah notifikasi duplikat.
     */
    public function generateAdaptiveNotifications(int $userId): void
    {
        $this->checkLowMastery($userId);
        $this->checkRepeatedMaterials($userId);
        $this->checkInactivity($userId);
        $this->checkProjectFeedback($userId);
        $this->checkAchievement($userId);
    }

    /**
     * Notifikasi jika ada topik dengan mastery < 45
     */
    private function checkLowMastery(int $userId): void
    {
        $lowMasteries = StudentTopicMastery::where('user_id', $userId)
            ->where('mastery_level', '<', 45)
            ->with('topic:id,title')
            ->get();

        foreach ($lowMasteries as $m) {
            if (!$m->topic) continue;

            $this->createIfNotExists($userId, [
                'type'    => 'recommendation',
                'title'   => '📚 Perlu Perhatian',
                'message' => "Mastery kamu di topik \"{$m->topic->title}\" masih "
                           . round($m->mastery_level) . "%. Yuk ulangi materinya!",
                'data'    => ['topic_id' => $m->topic_id, 'flag' => 'low_mastery'],
            ]);
        }
    }

    /**
     * Notifikasi jika siswa sering membuka materi yang sama (kesulitan)
     */
    private function checkRepeatedMaterials(int $userId): void
    {
        $repeated = InteractionLog::where('user_id', $userId)
            ->where('action', 'repeat_material')
            ->where('open_count', '>=', 3)
            ->with('topic:id,title', 'material:id,title')
            ->get();

        foreach ($repeated as $log) {
            $this->createIfNotExists($userId, [
                'type'    => 'recommendation',
                'title'   => '🔄 Materi Sering Dibuka Ulang',
                'message' => "Kamu sudah membuka materi \"{$log->material?->title}\" "
                           . "{$log->open_count}x. Coba kerjakan kuis untuk ukur pemahamanmu!",
                'data'    => ['material_id' => $log->material_id, 'flag' => 'repeated_material'],
            ]);
        }
    }

    /**
     * Notifikasi pengingat jika tidak aktif > 3 hari
     */
    private function checkInactivity(int $userId): void
    {
        $lastLog = LearningLog::where('user_id', $userId)
            ->latest()
            ->first();

        $daysSinceLastActivity = $lastLog
            ? now()->diffInDays($lastLog->created_at)
            : 999;

        if ($daysSinceLastActivity >= 3) {
            $this->createIfNotExists($userId, [
                'type'    => 'reminder',
                'title'   => '⏰ Sudah Lama Tidak Belajar',
                'message' => $daysSinceLastActivity >= 999
                    ? 'Belum ada aktivitas belajar. Yuk mulai belajar sekarang!'
                    : "Kamu tidak aktif selama {$daysSinceLastActivity} hari. "
                      . "Jangan sampai ketinggalan materi!",
                'data'    => ['flag' => 'inactivity_' . now()->toDateString()],
            ]);
        }
    }

    /**
     * Notifikasi saat guru sudah memberikan nilai proyek
     */
    private function checkProjectFeedback(int $userId): void
    {
        $gradedProjects = PblProject::where('user_id', $userId)
            ->where('status', 'graded')
            ->whereNotNull('score')
            ->get();

        foreach ($gradedProjects as $project) {
            $this->createIfNotExists($userId, [
                'type'    => 'feedback',
                'title'   => '✅ Proyek Sudah Dinilai',
                'message' => "Proyek \"{$project->title}\" mendapat nilai "
                           . "{$project->score}. Lihat feedback dari guru!",
                'data'    => ['project_id' => $project->id, 'flag' => 'project_graded'],
            ]);
        }
    }

    /**
     * Notifikasi achievement saat mastery >= 85
     */
    private function checkAchievement(int $userId): void
    {
        $highMasteries = StudentTopicMastery::where('user_id', $userId)
            ->where('mastery_level', '>=', 85)
            ->with('topic:id,title')
            ->get();

        foreach ($highMasteries as $m) {
            if (!$m->topic) continue;

            $this->createIfNotExists($userId, [
                'type'    => 'achievement',
                'title'   => '🏆 Topik Dikuasai!',
                'message' => "Selamat! Kamu sudah menguasai topik "
                           . "\"{$m->topic->title}\" dengan mastery "
                           . round($m->mastery_level) . "%!",
                'data'    => ['topic_id' => $m->topic_id, 'flag' => 'achievement'],
            ]);
        }
    }

    /**
     * Buat notifikasi hanya jika belum pernah dibuat dengan flag yang sama.
     * Mencegah notifikasi duplikat setiap kali siswa buka halaman.
     */
    private function createIfNotExists(int $userId, array $payload): void
    {
        $flag = $payload['data']['flag'] ?? null;
        if (!$flag) return;

        // Cek berdasarkan flag dan topic/material/project id jika ada
        $exists = AppNotification::where('user_id', $userId)
            ->where('type', $payload['type'])
            ->whereJsonContains('data->flag', $flag)
            ->when(isset($payload['data']['topic_id']), fn($q) =>
                $q->whereJsonContains('data->topic_id', $payload['data']['topic_id'])
            )
            ->when(isset($payload['data']['material_id']), fn($q) =>
                $q->whereJsonContains('data->material_id', $payload['data']['material_id'])
            )
            ->when(isset($payload['data']['project_id']), fn($q) =>
                $q->whereJsonContains('data->project_id', $payload['data']['project_id'])
            )
            ->exists();

        if (!$exists) {
            AppNotification::create([
                'user_id' => $userId,
                'type'    => $payload['type'],
                'title'   => $payload['title'],
                'message' => $payload['message'],
                'is_read' => false,
                'data'    => $payload['data'],
            ]);
        }
    }

    /**
     * Kirim notifikasi manual ke user manapun (siswa, guru, dst).
     */
    public function send(int $userId, string $title, string $message): void
    {
        AppNotification::create([
            'user_id' => $userId,
            'type'    => 'reminder',
            'title'   => $title,
            'message' => $message,
            'is_read' => false,
            'data'    => ['flag' => 'manual_' . now()->timestamp],
        ]);
    }

    /**
     * Alias lama, dipertahankan untuk kompatibilitas — dipanggil dari
     * TeacherController@notifyStudent. Perilakunya sama persis dengan send().
     */
    public function sendToStudent(int $studentId, string $title, string $message): void
    {
        $this->send($studentId, $title, $message);
    }
}