<?php
// app/Console/Commands/SendAdaptiveReminders.php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\LearningLog;
use App\Models\StudentTopicMastery;
use App\Models\InteractionLog;
use App\Models\TestResult;
use App\Models\Topic;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendAdaptiveReminders extends Command
{
    protected $signature   = 'adaptive:remind';
    protected $description = 'Kirim notifikasi proaktif AI ke semua siswa';

    public function handle(NotificationService $notifService): void
    {
        // Laravel 12: inject via handle(), bukan constructor
        $students = User::where('role', 'siswa')->get();

        $this->info("Memproses {$students->count()} siswa...");

        foreach ($students as $student) {
            $this->checkAndNotify($student, $notifService);
            $this->line("  ✓ {$student->name}");
        }

        $this->info('Selesai.');
    }

    private function checkAndNotify(User $student, NotificationService $notifService): void
    {
        $userId = $student->id;

        // 1. Tidak aktif >= 2 hari
        $lastLog   = LearningLog::where('user_id', $userId)->latest()->first();
        $daysSince = $lastLog
            ? now()->diffInDays($lastLog->created_at)
            : 999;

        if ($daysSince >= 2) {
            $notifService->createIfPublic($userId, [
                'type'    => 'reminder',
                'title'   => '⏰ Jangan Lupa Belajar!',
                'message' => $daysSince >= 999
                    ? 'Kamu belum pernah belajar. Yuk mulai sekarang!'
                    : "Sudah {$daysSince} hari kamu tidak belajar. "
                      . "Konsistensi adalah kunci keberhasilan!",
                'data'    => [
                    'flag'       => 'inactivity_' . now()->toDateString(),
                    'days_since' => $daysSince,
                ],
            ]);
        }

        // 2. Topik mastery < 45 yang belum diulang 3 hari
        $lowMasteries = StudentTopicMastery::where('user_id', $userId)
            ->where('mastery_level', '<', 45)
            ->where('last_accessed', '<', now()->subDays(3))
            ->with('topic:id,title')
            ->get();

        foreach ($lowMasteries as $m) {
            if (!$m->topic) continue;

            $notifService->createIfPublic($userId, [
                'type'    => 'recommendation',
                'title'   => '📖 Saatnya Mengulang Materi',
                'message' => "Mastery topik \"{$m->topic->title}\" kamu "
                           . round($m->mastery_level) . "% dan sudah "
                           . now()->diffInDays($m->last_accessed)
                           . " hari tidak diulang.",
                'data'    => [
                    'flag'     => 'low_mastery_reminder_' . now()->toDateString(),
                    'topic_id' => $m->topic_id,
                ],
            ]);
        }

        // 3. Topik yang sudah dibuka tapi belum pre-test
        $openedTopicIds = InteractionLog::where('user_id', $userId)
            ->where('action', 'open_topic')
            ->pluck('topic_id')
            ->unique();

        foreach ($openedTopicIds as $topicId) {
            $hasPreTest = TestResult::where('user_id', $userId)
                ->where('topic_id', $topicId)
                ->where('type', 'pre_test')
                ->exists();

            if (!$hasPreTest) {
                $topic = Topic::find($topicId);
                if (!$topic) continue;

                $notifService->createIfPublic($userId, [
                    'type'    => 'reminder',
                    'title'   => '📝 Pre-Test Belum Dikerjakan',
                    'message' => "Kamu sudah membuka topik \"{$topic->title}\" "
                               . "tapi belum mengerjakan pre-test.",
                    'data'    => [
                        'flag'     => 'pretest_reminder_' . $topicId,
                        'topic_id' => $topicId,
                    ],
                ]);
            }
        }
    }
}