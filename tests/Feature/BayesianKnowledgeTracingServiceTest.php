<?php

namespace Tests\Feature;

use App\Models\BktParameter;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\BayesianKnowledgeTracingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi BayesianKnowledgeTracingService. Nilai yang dibandingkan di
 * test-test bayesianUpdate/trace di bawah dihitung ULANG secara independen
 * di Python (bukan disalin dari kode PHP-nya) sebelum ditulis di sini,
 * supaya tes ini benar-benar memvalidasi rumus, bukan cuma "mengunci"
 * apa pun yang kebetulan dihasilkan kode.
 */
class BayesianKnowledgeTracingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BayesianKnowledgeTracingService $bkt;

    /** Params contoh dari literatur, dipakai di semua test perhitungan manual. */
    private array $params = ['p_l0' => 0.3, 'p_t' => 0.2, 'p_s' => 0.1, 'p_g' => 0.2];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bkt = app(BayesianKnowledgeTracingService::class);
    }

    public function test_update_satu_observasi_benar_sesuai_hitungan_manual(): void
    {
        $posterior = $this->bkt->bayesianUpdate(0.3, true, $this->params);

        // Dihitung manual (independen, Python): 0.7268292682926829
        $this->assertEqualsWithDelta(0.7268292682926829, $posterior, 1e-9);
    }

    public function test_update_satu_observasi_salah_sesuai_hitungan_manual(): void
    {
        $posterior = $this->bkt->bayesianUpdate(0.3, false, $this->params);

        // Dihitung manual (independen, Python): 0.24067796610169492
        $this->assertEqualsWithDelta(0.24067796610169492, $posterior, 1e-9);
    }

    public function test_urutan_semua_benar_mastery_naik_monoton_mendekati_1(): void
    {
        $trace = $this->bkt->predictMasterySequence([true, true, true], $this->params);

        $this->assertEqualsWithDelta(0.7268292682926829, $trace[0], 1e-9);
        $this->assertEqualsWithDelta(0.9383344803854095, $trace[1], 1e-9);
        $this->assertEqualsWithDelta(0.9884849555816158, $trace[2], 1e-9);

        // Monoton naik.
        $this->assertGreaterThan($trace[0], $trace[1]);
        $this->assertGreaterThan($trace[1], $trace[2]);
    }

    public function test_urutan_semua_salah_mastery_tetap_rendah(): void
    {
        $trace = $this->bkt->predictMasterySequence([false, false, false], $this->params);

        $this->assertEqualsWithDelta(0.24067796610169492, $trace[0], 1e-9);
        $this->assertEqualsWithDelta(0.23048845947396673, $trace[1], 1e-9);
        $this->assertEqualsWithDelta(0.22887159402262527, $trace[2], 1e-9);

        // Tetap jauh di bawah trace "semua benar" pada jumlah observasi yang sama.
        $allCorrect = $this->bkt->predictMasterySequence([true, true, true], $this->params);
        $this->assertLessThan($allCorrect[2], $trace[2]);
    }

    public function test_mastery_tidak_pernah_keluar_dari_rentang_0_sampai_1(): void
    {
        $trace = $this->bkt->predictMasterySequence(
            [true, false, true, true, false, false, true, false],
            $this->params
        );
        foreach ($trace as $l) {
            $this->assertGreaterThanOrEqual(0.0, $l);
            $this->assertLessThanOrEqual(1.0, $l);
        }
    }

    public function test_get_parameters_fallback_bertingkat_subjek_lalu_global_lalu_default(): void
    {
        $subject = Subject::create(['name' => 'Matematika', 'join_code' => 'BKT001']);

        // Belum ada parameter sama sekali -> pakai default.
        $this->assertEquals($this->bkt->defaultParameters(), $this->bkt->getParameters($subject->id));

        // Ada parameter GLOBAL -> dipakai untuk mapel manapun yang belum punya sendiri.
        BktParameter::create([
            'subject_id' => null,
            'p_l0' => 0.4, 'p_t' => 0.25, 'p_s' => 0.15, 'p_g' => 0.15,
            'fitted_from_sequences' => 20,
        ]);
        $this->assertEquals(0.4, $this->bkt->getParameters($subject->id)['p_l0']);
        $this->assertEquals(0.4, $this->bkt->getParameters(null)['p_l0']);

        // Ada parameter KHUSUS mapel ini -> lebih diprioritaskan daripada global.
        BktParameter::create([
            'subject_id' => $subject->id,
            'p_l0' => 0.6, 'p_t' => 0.1, 'p_s' => 0.05, 'p_g' => 0.1,
            'fitted_from_sequences' => 15,
        ]);
        $this->assertEquals(0.6, $this->bkt->getParameters($subject->id)['p_l0']);
        // Mapel lain yang belum punya parameter sendiri tetap pakai global.
        $this->assertEquals(0.4, $this->bkt->getParameters(999)['p_l0']);
    }

    public function test_fit_parameters_mengembalikan_default_kalau_tidak_ada_data(): void
    {
        $result = $this->bkt->fitParameters([]);
        $this->assertEquals($this->bkt->defaultParameters(), $result['params']);
        $this->assertSame(0, $result['sequences_used']);
    }

    public function test_fit_parameters_menemukan_pola_siswa_yang_konsisten_menguasai(): void
    {
        // Data sintetis: siswa yang SELALU benar sejak awal (pola "sudah
        // menguasai dari awal") berulang di banyak urutan -> P(L0) hasil
        // fitting seharusnya condong TINGGI untuk menjelaskan pola ini.
        $sequences = array_fill(0, 15, [true, true, true, true]);

        $result = $this->bkt->fitParameters($sequences);

        $this->assertGreaterThanOrEqual(0.7, $result['params']['p_l0']);
        $this->assertSame(15, $result['sequences_used']);
        $this->assertNotNull($result['log_likelihood']);
    }

    public function test_fit_parameters_menemukan_pola_siswa_yang_konsisten_belum_menguasai(): void
    {
        // Kebalikan dari test di atas: selalu salah -> P(L0) hasil fitting
        // seharusnya condong RENDAH.
        $sequences = array_fill(0, 15, [false, false, false, false]);

        $result = $this->bkt->fitParameters($sequences);

        $this->assertLessThanOrEqual(0.3, $result['params']['p_l0']);
    }

    /**
     * Test integrasi ujung-ke-ujung: benar-benar memanggil endpoint
     * POST /quizzes/{id}/submit (bukan cuma bikin baris manual) untuk
     * memastikan perubahan di QuizController sungguhan menghasilkan log
     * quiz_attempts yang bisa dipakai BKT — bukan cuma diasumsikan.
     */
    public function test_submit_kuis_sungguhan_tercatat_dan_bisa_dibaca_bkt_sebagai_urutan_observasi(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $subject = Subject::create(['name' => 'Animasi', 'join_code' => 'BKT002']);
        $subject->students()->attach($student->id, ['enrollment_type' => 'assigned', 'enrolled_at' => now()]);
        $topic = Topic::create(['title' => 'Prinsip Animasi', 'order' => 1, 'subject_id' => $subject->id]);
        $quiz = Quiz::create([
            'topic_id' => $topic->id, 'title' => 'Kuis 1', 'type' => 'regular', 'passing_score' => 70,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id, 'question' => '2 + 2?', 'options' => ['3', '4'],
            'correct_answer' => '4',
        ]);

        // Percobaan 1: jawab benar (skor 100, lulus).
        $this->actingAs($student)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'answers' => [(string) $question->id => '4'],
        ])->assertStatus(200)->assertJsonPath('passed', true);

        // Percobaan 2 (ulang kuis yang sama): jawab salah (skor 0, gagal).
        $this->actingAs($student)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'answers' => [(string) $question->id => '3'],
        ])->assertStatus(200)->assertJsonPath('passed', false);

        $this->assertSame(2, QuizAttempt::count());

        $sequence = $this->bkt->observationSequenceFor($student->id, $topic->id);
        $this->assertSame([true, false], $sequence);
    }

    /**
     * Test integrasi endpoint GET /mastery — memastikan
     * bkt_mastery_probability benar-benar muncul di respons API yang
     * dilihat Flutter, dan mastery_level (heuristik lama) tetap ada
     * tanpa berubah nilainya (aditif, bukan menggantikan).
     */
    public function test_endpoint_mastery_menyertakan_bkt_mastery_probability_di_samping_mastery_level(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);
        $subject = Subject::create(['name' => 'Animasi', 'join_code' => 'BKT005']);
        $subject->students()->attach($student->id, ['enrollment_type' => 'assigned', 'enrolled_at' => now()]);
        $topic = Topic::create(['title' => 'Prinsip Animasi', 'order' => 1, 'subject_id' => $subject->id]);
        $quiz = Quiz::create(['topic_id' => $topic->id, 'title' => 'Kuis 1', 'type' => 'regular', 'passing_score' => 70]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id, 'question' => '2 + 2?', 'options' => ['3', '4'], 'correct_answer' => '4',
        ]);

        // Belum ada percobaan kuis sama sekali -> bkt_mastery_probability harus null.
        $before = $this->actingAs($student)->getJson("/api/mastery?subject_id={$subject->id}");
        $before->assertStatus(200);
        $this->assertEmpty($before->json());

        $this->actingAs($student)->postJson("/api/quizzes/{$quiz->id}/submit", [
            'answers' => [(string) $question->id => '4'],
        ])->assertStatus(200);

        $after = $this->actingAs($student)->getJson("/api/mastery?subject_id={$subject->id}");
        $after->assertStatus(200);
        $row = $after->json()[0];

        $this->assertArrayHasKey('mastery_level', $row);
        $this->assertArrayHasKey('bkt_mastery_probability', $row);
        $this->assertSame(1, $row['bkt_observations_count']);
        $this->assertNotNull($row['bkt_mastery_probability']);
        $this->assertGreaterThan(0, $row['bkt_mastery_probability']);
    }

    public function test_collect_sequences_for_subject_mengelompokkan_per_siswa_dan_topik_dalam_satu_mapel(): void
    {
        $subjectA = Subject::create(['name' => 'Animasi', 'join_code' => 'BKT003']);
        $subjectB = Subject::create(['name' => 'Matematika', 'join_code' => 'BKT004']);
        $topicA = Topic::create(['title' => 'Topik A', 'order' => 1, 'subject_id' => $subjectA->id]);
        $topicB = Topic::create(['title' => 'Topik B', 'order' => 1, 'subject_id' => $subjectB->id]);
        $quizA = Quiz::create(['topic_id' => $topicA->id, 'title' => 'Kuis A', 'type' => 'regular', 'passing_score' => 70]);
        $quizB = Quiz::create(['topic_id' => $topicB->id, 'title' => 'Kuis B', 'type' => 'regular', 'passing_score' => 70]);
        $student = User::factory()->create(['role' => 'siswa']);

        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quizA->id, 'topic_id' => $topicA->id,
            'quiz_type' => 'regular', 'score' => 90, 'passed' => true,
        ]);
        QuizAttempt::create([
            'user_id' => $student->id, 'quiz_id' => $quizB->id, 'topic_id' => $topicB->id,
            'quiz_type' => 'regular', 'score' => 40, 'passed' => false,
        ]);

        $sequencesA = $this->bkt->collectSequencesForSubject($subjectA->id);
        $sequencesB = $this->bkt->collectSequencesForSubject($subjectB->id);

        $this->assertEquals([[true]], $sequencesA);
        $this->assertEquals([[false]], $sequencesB);
    }
}
