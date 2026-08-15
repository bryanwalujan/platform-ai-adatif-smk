<?php
// app/Http/Controllers/Api/ExportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentTopicMastery;
use App\Models\User;
use App\Models\LearningLog;
use App\Models\TestResult;
use App\Services\SubjectAccessService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(private SubjectAccessService $access)
    {
    }

    /**
     * GET /export/my-progress
     * Siswa export progress dirinya sendiri sebagai CSV. Opsional
     * ?subject_id= untuk batasi ke satu mapel; default semua mapel siswa
     * (sama seperti perilaku lama, yang memang selalu lintas-mapel).
     */
    public function myProgress(Request $request)
    {
        $user = $request->user();

        $subjectIds = $request->filled('subject_id')
            ? [tap((int) $request->subject_id, fn ($id) => $this->access->assertEnrolled($user, $id))]
            : $this->access->studentSubjectIds($user);

        $masteries = StudentTopicMastery::where('user_id', $user->id)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->get();

        $logs = LearningLog::where('user_id', $user->id)
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->latest()
            ->get();

        $preTests  = TestResult::where('user_id', $user->id)
            ->where('type', 'pre_test')
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->with('topic:id,title')
            ->get();

        $postTests = TestResult::where('user_id', $user->id)
            ->where('type', 'post_test')
            ->whereHas('topic', fn ($q) => $q->whereIn('subject_id', $subjectIds))
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
     * Guru export data mastery siswa sebagai CSV.
     *
     * PERBAIKAN: dulu ini mengekspor SEMUA siswa di seluruh sistem tanpa
     * filter apapun (User::where('role','siswa')) — guru manapun bisa
     * lihat & unduh data siswa dari mapel guru lain. Sekarang dibatasi ke
     * siswa yang terdaftar di mata pelajaran yang diampu guru ini saja.
     * Opsional ?subject_id= untuk batasi ke satu mapel spesifik.
     */
    public function allStudents(Request $request)
    {
        $user = $request->user();

        $subjectIds = $request->filled('subject_id')
            ? [tap((int) $request->subject_id, fn ($id) => $this->access->assertTeaches($user, $id))]
            : $this->access->teacherSubjectIds($user);

        $students = User::where('role', 'siswa')
            ->whereHas('subjectsEnrolled', fn ($q) => $q->whereIn('subjects.id', $subjectIds))
            ->with(['studentMasteries' => fn ($q) => $q->whereHas('topic', fn ($t) => $t->whereIn('subject_id', $subjectIds))->with('topic')])
            ->get();

        $csv  = "LAPORAN MASTERY SISWA\n";
        $csv .= "Diekspor oleh," . $user->name . "\n";
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
