<?php

namespace Tests\Feature;

use App\Models\StudentTopicMastery;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\AdaptiveEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi manual untuk overhaul AI Adaptif (2026-08-16): bonus mastery
 * tidak lagi menumpuk tanpa batas, decay "lupa" dipakai di baca-saja,
 * rekomendasi mengikuti urutan kurikulum + prioritas + limit, dan level PBL
 * mempertimbangkan cakupan topik.
 */
class AdaptiveEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdaptiveEngineService $engine;
    private Subject $subject;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine  = app(AdaptiveEngineService::class);
        $this->subject = Subject::create([
            'name' => 'Animasi 2D',
            'join_code' => 'ABC123',
        ]);
        $this->student = User::factory()->create(['role' => 'siswa']);
    }

    private function makeTopic(string $title, int $order): Topic
    {
        return Topic::create([
            'title'      => $title,
            'order'      => $order,
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_bonus_tidak_menumpuk_tanpa_batas_lintas_banyak_percobaan(): void
    {
        $topic = $this->makeTopic('Prinsip Animasi', 1);

        // Submit kuis nilai 80 + waktu 30 menit berkali-kali. Dengan bug lama
        // (+5/+3 waktu, +7/+3 nilai ditambah LANGSUNG ke mastery tiap kali),
        // mastery akan cepat mentok 100 walau nilai kuis cuma 80. Dengan
        // perbaikan (bonus dilebur ke effective score, dibatasi maks 100
        // sebelum masuk weighted-average), mastery seharusnya konvergen ke
        // sekitar effective score (80 + bonus, di-cap 100), bukan mentok 100.
        for ($i = 0; $i < 10; $i++) {
            $mastery = $this->engine->updateMastery($this->student->id, $topic->id, 80, 30);
        }

        // effective score = min(100, 80 + 4 waktu + 2 nilai) = 86. Weighted
        // average dari 0 mendekati 86 secara asimtotik: m_n = 86*(1-0.6^n).
        // Setelah 10x submit seharusnya sudah sangat dekat ke 86, dan yang
        // terpenting jauh di bawah 100 (bug lama akan mentok di 100).
        $expected = 86 * (1 - 0.6 ** 10);
        $this->assertEqualsWithDelta($expected, $mastery->mastery_level, 0.01);
        $this->assertLessThan(90, $mastery->mastery_level);
    }

    public function test_mastery_effective_meluruh_setelah_lama_tidak_disentuh_tapi_nilai_mentah_tetap(): void
    {
        $topic   = $this->makeTopic('Storyboard', 1);
        $mastery = StudentTopicMastery::create([
            'user_id'       => $this->student->id,
            'topic_id'      => $topic->id,
            'mastery_level' => 90,
            'attempts'      => 3,
            'last_accessed' => now()->subDays(34), // 14 hari grace + 20 hari decay
        ]);

        // decay = min(90*0.4, 20*1.0) = min(36, 20) = 20 -> 90-20 = 70
        $this->assertEquals(70.0, $this->engine->effectiveMastery($mastery));
        // Nilai mentah di DB tidak berubah (bukan mutasi destruktif)
        $this->assertEquals(90.0, $mastery->fresh()->mastery_level);
    }

    public function test_mastery_effective_tidak_meluruh_dalam_masa_bebas_lupa(): void
    {
        $topic   = $this->makeTopic('Storyboard', 1);
        $mastery = StudentTopicMastery::create([
            'user_id'       => $this->student->id,
            'topic_id'      => $topic->id,
            'mastery_level' => 90,
            'attempts'      => 3,
            'last_accessed' => now()->subDays(10),
        ]);

        $this->assertEquals(90.0, $this->engine->effectiveMastery($mastery));
    }

    public function test_decay_dibatasi_maksimum_40_persen_tidak_pernah_sampai_nol(): void
    {
        $topic   = $this->makeTopic('Storyboard', 1);
        $mastery = StudentTopicMastery::create([
            'user_id'       => $this->student->id,
            'topic_id'      => $topic->id,
            'mastery_level' => 90,
            'attempts'      => 3,
            'last_accessed' => now()->subDays(365), // sangat lama
        ]);

        // decay dibatasi 40% dari 90 = 36, jadi minimal 54, tidak jatuh ke 0
        $this->assertEquals(54.0, $this->engine->effectiveMastery($mastery));
    }

    public function test_rekomendasi_topik_baru_ikut_urutan_kurikulum(): void
    {
        $this->makeTopic('Topik Lanjut (order 5)', 5);
        $topikDasar = $this->makeTopic('Topik Dasar (order 1)', 1);
        $this->makeTopic('Topik Menengah (order 3)', 3);

        $recs = $this->engine->getRecommendations($this->student->id, [$this->subject->id]);

        $newRec = collect($recs)->firstWhere('type', 'new');
        $this->assertNotNull($newRec);
        $this->assertEquals($topikDasar->id, $newRec['topic']['id']);
    }

    public function test_rekomendasi_diurutkan_prioritas_dan_dibatasi_maksimum(): void
    {
        // 7 topik mastery rendah (high priority) -> harus dipotong ke 5
        for ($i = 1; $i <= 7; $i++) {
            $topic = $this->makeTopic("Topik $i", $i);
            StudentTopicMastery::create([
                'user_id'       => $this->student->id,
                'topic_id'      => $topic->id,
                'mastery_level' => 20, // < 45 -> priority high
                'attempts'      => 1,
                'last_accessed' => now(),
            ]);
        }

        $recs = $this->engine->getRecommendations($this->student->id, [$this->subject->id]);

        $this->assertCount(5, $recs);
        foreach ($recs as $r) {
            $this->assertEquals('high', $r['priority']);
        }
    }

    public function test_spaced_repetition_menyarankan_ulang_topik_mastery_tinggi_yang_lama_tak_disentuh(): void
    {
        $topic = $this->makeTopic('Topik Lama', 1);
        StudentTopicMastery::create([
            'user_id'       => $this->student->id,
            'topic_id'      => $topic->id,
            'mastery_level' => 90,
            'attempts'      => 5,
            'last_accessed' => now()->subDays(25), // >= 21 hari, tapi masih < grace+decay penuh
        ]);

        $recs = $this->engine->getRecommendations($this->student->id, [$this->subject->id]);
        $refreshRec = collect($recs)->firstWhere('type', 'refresh');

        $this->assertNotNull($refreshRec);
        $this->assertEquals('low', $refreshRec['priority']);
    }

    public function test_pbl_level_butuh_cakupan_topik_bukan_cuma_rata_rata_tinggi(): void
    {
        // 5 topik total di mapel ini, siswa baru coba 1 dengan nilai sempurna.
        $t1 = $this->makeTopic('T1', 1);
        $this->makeTopic('T2', 2);
        $this->makeTopic('T3', 3);
        $this->makeTopic('T4', 4);
        $this->makeTopic('T5', 5);

        StudentTopicMastery::create([
            'user_id'       => $this->student->id,
            'topic_id'      => $t1->id,
            'mastery_level' => 100,
            'attempts'      => 1,
            'last_accessed' => now(),
        ]);

        // Rata-rata 100 tapi coverage cuma 1/5 = 0.2 -> tidak cukup untuk "Lanjutan" (butuh >=0.6)
        $this->assertEquals('Dasar', $this->engine->getPBLLevel($this->student->id, [$this->subject->id]));
    }

    public function test_pbl_level_lanjutan_ketika_rata_rata_tinggi_dan_cakupan_cukup(): void
    {
        $topics = collect(range(1, 5))->map(fn ($i) => $this->makeTopic("T$i", $i));

        foreach ($topics->take(4) as $t) { // 4/5 = 0.8 coverage
            StudentTopicMastery::create([
                'user_id'       => $this->student->id,
                'topic_id'      => $t->id,
                'mastery_level' => 90,
                'attempts'      => 2,
                'last_accessed' => now(),
            ]);
        }

        $this->assertEquals('Lanjutan', $this->engine->getPBLLevel($this->student->id, [$this->subject->id]));
    }
}
