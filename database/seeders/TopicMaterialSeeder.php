<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicMaterialSeeder extends Seeder
{
    public function run(): void
    {
        // Matikan sementara pengecekan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Hapus data lama
        Topic::truncate();
        Material::truncate();
        Quiz::truncate();
        QuizQuestion::truncate();

        // Nyalakan kembali pengecekan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ================= TOPIK =================
        $topics = [
            [
                'title' => 'Dasar Animasi 2D',
                'description' => 'Pengantar animasi dua dimensi dan prinsip dasar',
                'order' => 1
            ],
            [
                'title' => 'Rigging dan Karakter',
                'description' => 'Membuat tulang karakter dan gerakan',
                'order' => 2
            ],
            [
                'title' => 'Animasi Lip Sync',
                'description' => 'Sinkronisasi gerak mulut dengan suara',
                'order' => 3
            ],
            [
                'title' => 'Motion Graphics',
                'description' => 'Animasi teks, shape, dan efek',
                'order' => 4
            ],
        ];

        foreach ($topics as $t) {
            $topic = Topic::create($t);

            // ================= MATERI =================
            $materials = [
                [
                    'title' => 'Pengertian Animasi dan Frame Rate',
                    'content' => 'Animasi adalah seni menciptakan ilusi gerak melalui serangkaian gambar yang ditampilkan secara berurutan.',
                    'video_url' => 'https://www.youtube.com/embed/dQw4w9wgxcq', // contoh
                    'duration_minutes' => 15,
                    'order' => 1
                ],
                [
                    'title' => '12 Prinsip Animasi Dasar',
                    'content' => 'Squash and Stretch, Anticipation, Staging, Straight Ahead Action and Pose-to-Pose, Follow Through and Overlapping Action, dll.',
                    'video_url' => 'https://www.youtube.com/embed/example2',
                    'duration_minutes' => 25,
                    'order' => 2
                ],
            ];

            foreach ($materials as $m) {
                $topic->materials()->create($m);
            }

            // ================= KUIS =================
            $quiz = Quiz::create([
                'topic_id' => $topic->id,
                'title' => 'Kuis ' . $topic->title,
                'time_limit_minutes' => 20,
                'passing_score' => 70,
            ]);

            // Soal kuis
            $questions = [
                [
                    'question' => 'Berapa frame per detik yang umum digunakan di animasi sinema?',
                    'options' => json_encode(['A. 24 fps', 'B. 30 fps', 'C. 25 fps', 'D. 60 fps']),
                    'correct_answer' => 'A',
                    'explanation' => '24 fps adalah standar sinema internasional.'
                ],
                [
                    'question' => 'Apa itu prinsip "Squash and Stretch"?',
                    'options' => json_encode(['A. Prinsip membuat objek terlihat hidup', 'B. Teknik menghapus gambar', 'C. Cara memberi warna', 'D. Metode rendering']),
                    'correct_answer' => 'A',
                    'explanation' => 'Salah satu dari 12 prinsip animasi dasar oleh Disney.'
                ],
            ];

            foreach ($questions as $q) {
                $quiz->questions()->create($q);
            }
        }

        $this->command->info('✅ Data Topik, Materi, dan Kuis berhasil dibuat!');
    }
}