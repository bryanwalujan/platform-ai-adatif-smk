<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Nama & deskripsi mata pelajaran default yang dibuat untuk menampung
     * seluruh data lama (single-subject "Animasi") supaya tidak ada yang
     * hilang/terkunci begitu sistem menjadi multi-mapel.
     */
    private const DEFAULT_SUBJECT_NAME = 'Animasi';

    /**
     * Run the migrations.
     *
     * Data migration — bukan sekadar perubahan skema. Dibungkus transaction
     * supaya atomik: kalau ada langkah yang gagal, semua di-rollback,
     * tidak ada state setengah-jadi di data produksi.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            // 1) Buat mata pelajaran default. created_by = guru pertama yang
            //    terdaftar (kalau ada); tetap jalan (null) kalau belum ada guru sama sekali.
            $firstTeacherId = DB::table('users')->where('role', 'guru')->orderBy('id')->value('id');

            $subjectId = DB::table('subjects')->insertGetId([
                'name'        => self::DEFAULT_SUBJECT_NAME,
                'description' => 'Mata pelajaran default, dibuat otomatis saat migrasi ke sistem multi-mapel. '
                                . 'Menampung seluruh topik/materi/kuis/proyek yang sudah ada sebelumnya.',
                'join_code'   => $this->generateUniqueJoinCode(),
                'created_by'  => $firstTeacherId,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // 2) Semua guru existing jadi co-teacher mata pelajaran default ini.
            $teacherIds = DB::table('users')->where('role', 'guru')->pluck('id');
            if ($teacherIds->isNotEmpty()) {
                DB::table('subject_teacher')->insert(
                    $teacherIds->map(fn ($id) => [
                        'subject_id' => $subjectId,
                        'user_id'    => $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            // 3) Semua siswa existing di-enroll ke mata pelajaran default ini
            //    (enrollment_type 'assigned' karena mereka tidak "join" via kode,
            //    mereka sudah ada di sistem sebelum konsep join-code ini dibuat).
            $studentIds = DB::table('users')->where('role', 'siswa')->pluck('id');
            if ($studentIds->isNotEmpty()) {
                DB::table('subject_student')->insert(
                    $studentIds->map(fn ($id) => [
                        'subject_id'      => $subjectId,
                        'user_id'         => $id,
                        'enrollment_type' => 'assigned',
                        'enrolled_at'     => $now,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ])->all()
                );
            }

            // 4) Semua topik lama (belum punya subject_id) di-attach ke mata pelajaran default.
            DB::table('topics')->whereNull('subject_id')->update(['subject_id' => $subjectId]);

            // 5) Semua proyek PBL lama diisi subject_id: turunkan dari topic-nya kalau
            //    ada, kalau proyeknya tidak punya topic_id (legacy), pakai subject default.
            $topicSubjectMap = DB::table('topics')->pluck('subject_id', 'id');

            DB::table('pbl_projects')->whereNull('subject_id')->orderBy('id')
                ->select('id', 'topic_id')
                ->chunkById(200, function ($rows) use ($topicSubjectMap, $subjectId) {
                    foreach ($rows as $row) {
                        $resolvedSubjectId = ($row->topic_id && isset($topicSubjectMap[$row->topic_id]))
                            ? $topicSubjectMap[$row->topic_id]
                            : $subjectId;

                        DB::table('pbl_projects')
                            ->where('id', $row->id)
                            ->update(['subject_id' => $resolvedSubjectId]);
                    }
                });
        });
    }

    /**
     * Reverse the migrations.
     *
     * Sengaja tidak destruktif: menghapus subject default berarti melepas
     * FK dari topics/pbl_projects (jadi NULL, aman karena kolomnya nullable)
     * dan menghapus baris pivot enrollment. Tidak menghapus/mengubah data
     * inti (topics/pbl_projects/users) itu sendiri.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $subjectId = DB::table('subjects')->where('name', self::DEFAULT_SUBJECT_NAME)->value('id');

            if (! $subjectId) {
                return;
            }

            DB::table('pbl_projects')->where('subject_id', $subjectId)->update(['subject_id' => null]);
            DB::table('topics')->where('subject_id', $subjectId)->update(['subject_id' => null]);
            DB::table('subject_student')->where('subject_id', $subjectId)->delete();
            DB::table('subject_teacher')->where('subject_id', $subjectId)->delete();
            DB::table('subjects')->where('id', $subjectId)->delete();
        });
    }

    /**
     * Generate kode kelas unik (6 karakter alfanumerik kapital, mis. "K3F9QX").
     */
    private function generateUniqueJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (DB::table('subjects')->where('join_code', $code)->exists());

        return $code;
    }
};
