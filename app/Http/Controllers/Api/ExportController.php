<?php
// app/Http/Controllers/Api/ExportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Models\User;
use App\Models\LearningLog;
use App\Models\TestResult;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    /**
     * GET /export/my-progress
     * Siswa export progress dirinya sendiri sebagai CSV
     */
    public function myProgress(Request $request)
    {
        $user     = $request->user();
        $masteries = StudentTopicMastery::where('user_id', $user->id)
            ->with('topic:id,title')
            ->get();

        $logs = LearningLog::where('user_id', $user->id)
            ->with('topic:id,title')
            ->latest()
            ->get();

        $preTests  = TestResult::where('user_id', $user->id)
            ->where('type', 'pre_test')
            ->with('topic:id,title')
            ->get();

        $postTests = TestResult::where('user_id', $user->id)
            ->where('type', 'post_test')
            ->with('topic:id,title')
            ->get();

        $avgMastery = $masteries->avg('mastery_level') ?? 0;
        $pblLevel   = match(true) {
            $avgMastery >= 85 => 'Lanjutan',
            $avgMastery >= 65 => 'Menengah',
            default           => 'Dasar',
        };

        // Build CSV
        $csv = "LAPORAN PROGRESS BELAJAR\n";
        $csv .= "Nama,{$user->name}\n";
        $csv .= "Email,{$user->email}\n";
        $csv .= "Tanggal Export," . now()->format('d/m/Y H:i') . "\n";
        $csv .= "Rata-rata Mastery," . round($avgMastery, 1) . "%\n";
        $csv .= "Level PBL,{$pblLevel}\n\n";

        // Mastery per topik
        $csv .= "MASTERY PER TOPIK\n";
        $csv .= "Topik,Mastery Level,Percobaan,Terakhir Diakses\n";
        foreach ($masteries as $m) {
            $csv .= "\"{$m->topic?->title}\","
                  . round($m->mastery_level, 1) . "%,"
                  . $m->attempts . ","
                  . ($m->last_accessed?->format('d/m/Y') ?? '-') . "\n";
        }

        // Pre-test vs Post-test
        $csv .= "\nHASIL PRE-TEST & POST-TEST\n";
        $csv .= "Topik,Tipe,Skor,Tanggal\n";
        foreach ($preTests->merge($postTests)->sortBy('topic_id') as $t) {
            $csv .= "\"{$t->topic?->title}\","
                  . ($t->type === 'pre_test' ? 'Pre-Test' : 'Post-Test') . ","
                  . $t->score . ","
                  . $t->created_at->format('d/m/Y') . "\n";
        }

        // Riwayat belajar
        $csv .= "\nRIWAYAT BELAJAR\n";
        $csv .= "Topik,Waktu Belajar (menit),Tanggal\n";
        foreach ($logs as $log) {
            $csv .= "\"{$log->topic?->title}\","
                  . $log->time_spent_minutes . ","
                  . $log->created_at->format('d/m/Y') . "\n";
        }

        $filename = 'progress_' . str_replace(' ', '_', $user->name)
                  . '_' . now()->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * GET /guru/export/students
     * Guru export semua data mastery siswa sebagai CSV
     */
    public function allStudents(Request $request)
    {
        $students = User::where('role', 'siswa')
            ->with(['studentMasteries.topic'])
            ->get();

        $csv  = "LAPORAN MASTERY SELURUH SISWA\n";
        $csv .= "Diekspor oleh," . $request->user()->name . "\n";
        $csv .= "Tanggal," . now()->format('d/m/Y H:i') . "\n\n";

        $csv .= "Nama Siswa,Email,Rata-rata Mastery,Level PBL,"
              . "Topik Dikuasai,Total Topik\n";

        foreach ($students as $s) {
            $avg      = $s->studentMasteries->avg('mastery_level') ?? 0;
            $level    = match(true) {
                $avg >= 85 => 'Lanjutan',
                $avg >= 65 => 'Menengah',
                default    => 'Dasar',
            };
            $mastered = $s->studentMasteries
                ->where('mastery_level', '>=', 75)->count();
            $total    = $s->studentMasteries->count();

            $csv .= "\"{$s->name}\",{$s->email},"
                  . round($avg, 1) . "%,{$level},"
                  . "{$mastered},{$total}\n";
        }

        // Detail per siswa per topik
        $csv .= "\nDETAIL MASTERY PER SISWA PER TOPIK\n";
        $csv .= "Nama Siswa,Topik,Mastery,Percobaan\n";

        foreach ($students as $s) {
            foreach ($s->studentMasteries as $m) {
                $csv .= "\"{$s->name}\","
                      . "\"{$m->topic?->title}\","
                      . round($m->mastery_level, 1) . "%,"
                      . $m->attempts . "\n";
            }
        }

        $filename = 'mastery_siswa_' . now()->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}