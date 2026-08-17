<?php

namespace App\Services;

use App\Models\BktParameter;
use App\Models\QuizAttempt;

/**
 * Bayesian Knowledge Tracing (BKT) — Corbett & Anderson (1994).
 *
 * Model probabilistik untuk memperkirakan probabilitas seorang siswa sudah
 * MENGUASAI suatu topik (state laten biner: menguasai / belum), diperbarui
 * tiap kali ada observasi baru (benar/salah pada satu percobaan kuis) lewat
 * aturan Bayes, ditambah satu langkah transisi belajar.
 *
 * INI BERBEDA dari AdaptiveEngineService::updateMastery() yang sudah ada
 * sebelumnya (rata-rata tertimbang + peluruhan waktu dengan rumus TETAP,
 * ditentukan developer). Di BKT, empat parameter di bawah ini:
 *
 *   - p_l0 : P(siswa sudah menguasai topik SEBELUM ada percobaan sama sekali)
 *   - p_t  : P(siswa BERPINDAH dari belum-menguasai -> menguasai
 *            setelah satu kesempatan belajar/kuis) — "learning rate"
 *   - p_s  : P(siswa yang SUDAH menguasai tetap menjawab salah — "slip")
 *   - p_g  : P(siswa yang BELUM menguasai tetap menjawab benar — "guess")
 *
 * DIESTIMASI DARI DATA historis (lihat fitParameters() / perintah Artisan
 * `bkt:fit`), bukan ditebak developer — itulah bagian "pembelajaran mesin"
 * pada algoritma ini: parameter model dipelajari dari pola jawaban siswa
 * yang sesungguhnya, bukan rumus tetap.
 *
 * CATATAN DESAIN: BKT klasik TIDAK memodelkan "lupa" (once learned, always
 * learned) — beda dengan decay pada AdaptiveEngineService. Kedua pendekatan
 * ini sengaja dijalankan BERDAMPINGAN (lihat AdaptiveEngineService yang
 * memanggil service ini secara opsional/aditif), bukan saling
 * menggantikan — supaya perilaku mastery_level yang sudah teruji & berjalan
 * di produksi tidak berubah tiba-tiba.
 */
class BayesianKnowledgeTracingService
{
    /**
     * Nilai default sebelum ada parameter yang di-fit dari data (cold
     * start) — angka umum dipakai pada literatur/pyBKT sebagai titik awal
     * yang wajar, BUKAN hasil fitting.
     */
    public function defaultParameters(): array
    {
        return ['p_l0' => 0.3, 'p_t' => 0.2, 'p_s' => 0.1, 'p_g' => 0.2];
    }

    /**
     * Ambil parameter BKT yang dipakai untuk satu mata pelajaran, dengan
     * fallback bertingkat: parameter khusus mapel itu -> parameter global
     * (subject_id null) -> nilai default. Pola fallback ini sama dengan
     * SubjectAccessService::resolveSubjectId di bagian lain aplikasi.
     */
    public function getParameters(?int $subjectId): array
    {
        if ($subjectId !== null) {
            $specific = BktParameter::where('subject_id', $subjectId)->first();
            if ($specific) {
                return $specific->toParamArray();
            }
        }

        $global = BktParameter::whereNull('subject_id')->first();
        if ($global) {
            return $global->toParamArray();
        }

        return $this->defaultParameters();
    }

    /**
     * Satu langkah update Bayesian: dari probabilitas penguasaan SEBELUM
     * observasi ini ($priorL), hitung probabilitas SESUDAH melihat hasil
     * satu percobaan (benar/salah), lalu terapkan transisi belajar.
     */
    public function bayesianUpdate(float $priorL, bool $correct, array $params): float
    {
        ['p_t' => $pt, 'p_s' => $ps, 'p_g' => $pg] = $params;

        if ($correct) {
            $numerator = $priorL * (1 - $ps);
            $denominator = $numerator + (1 - $priorL) * $pg;
        } else {
            $numerator = $priorL * $ps;
            $denominator = $numerator + (1 - $priorL) * (1 - $pg);
        }

        // Denominator cuma 0 kalau priorL persis 0 atau 1 dan parameter di
        // titik ekstrem — jaga-jaga pembagian nol, kembalikan prior apa
        // adanya (tidak ada informasi baru untuk memperbarui keyakinan).
        $posterior = $denominator > 0 ? $numerator / $denominator : $priorL;

        // Transisi belajar: walau observasi ini "belum menguasai", tetap
        // ada kemungkinan siswa belajar dari percobaan ini.
        return $posterior + (1 - $posterior) * $pt;
    }

    /**
     * Jalankan satu urutan observasi (array of bool, true = benar) lewat
     * bayesianUpdate() berturut-turut, kembalikan mastery SETELAH tiap
     * observasi (index 0 = setelah observasi pertama, dst).
     */
    public function predictMasterySequence(array $observations, array $params): array
    {
        $l = $params['p_l0'];
        $trace = [];
        foreach ($observations as $correct) {
            $l = $this->bayesianUpdate($l, (bool) $correct, $params);
            $trace[] = $l;
        }
        return $trace;
    }

    /**
     * Log-likelihood total sekumpulan urutan observasi di bawah satu set
     * parameter — dipakai fitParameters() untuk membandingkan seberapa
     * cocok satu kandidat parameter menjelaskan data historis yang
     * sesungguhnya (semakin tinggi/mendekati 0, semakin cocok).
     */
    public function logLikelihood(array $sequences, array $params): float
    {
        $total = 0.0;
        foreach ($sequences as $sequence) {
            $l = $params['p_l0'];
            foreach ($sequence as $correct) {
                $pCorrect = $l * (1 - $params['p_s']) + (1 - $l) * $params['p_g'];
                $pObserved = $correct ? $pCorrect : (1 - $pCorrect);
                // Jaga-jaga log(0) kalau probabilitas mentok di titik ekstrem.
                $pObserved = max($pObserved, 1e-9);
                $total += log($pObserved);
                $l = $this->bayesianUpdate($l, (bool) $correct, $params);
            }
        }
        return $total;
    }

    /**
     * Estimasi parameter BKT dari data historis lewat grid search —
     * mencoba kombinasi (p_l0, p_t, p_s, p_g) pada kisi nilai yang masuk
     * akal secara teori (p_s dan p_g dibatasi di bawah 0,5 sesuai syarat
     * identifiability model BKT standar), pilih kombinasi dengan
     * log-likelihood tertinggi terhadap $sequences.
     *
     * Grid search dipilih (bukan Expectation-Maximization penuh) karena
     * jauh lebih sederhana untuk diverifikasi benar dan cukup untuk skala
     * data awal platform ini — trade-off ini dicatat sebagai batasan
     * penelitian, bukan disembunyikan.
     *
     * @param array<int, array<int, bool>> $sequences tiap elemen = satu
     *   urutan observasi benar/salah milik satu siswa pada satu topik,
     *   diurutkan dari percobaan paling lama ke paling baru.
     * @return array{params: array, log_likelihood: float, sequences_used: int}
     */
    public function fitParameters(array $sequences): array
    {
        $sequences = array_values(array_filter($sequences, fn ($s) => count($s) > 0));

        if (empty($sequences)) {
            return [
                'params' => $this->defaultParameters(),
                'log_likelihood' => null,
                'sequences_used' => 0,
            ];
        }

        $grid = fn (float $from, float $to, float $step) => range($from, $to, $step);

        $bestParams = null;
        $bestLL = -INF;

        foreach ($grid(0.1, 0.9, 0.2) as $pl0) {
            foreach ($grid(0.05, 0.45, 0.1) as $pt) {
                foreach ($grid(0.05, 0.35, 0.1) as $ps) {
                    foreach ($grid(0.05, 0.35, 0.1) as $pg) {
                        $params = ['p_l0' => $pl0, 'p_t' => $pt, 'p_s' => $ps, 'p_g' => $pg];
                        $ll = $this->logLikelihood($sequences, $params);
                        if ($ll > $bestLL) {
                            $bestLL = $ll;
                            $bestParams = $params;
                        }
                    }
                }
            }
        }

        return [
            'params' => $bestParams,
            'log_likelihood' => $bestLL,
            'sequences_used' => count($sequences),
        ];
    }

    /**
     * Ambil urutan observasi benar/salah historis (diurutkan dari yang
     * paling lama) untuk SATU siswa pada SATU topik, dari tabel
     * quiz_attempts (log SETIAP percobaan kuis — regular, pre_test, dan
     * post_test — bukan test_results yang cuma menyimpan satu baris
     * pre_test dan satu post_test per topik, terlalu sedikit untuk
     * melacak proses belajar). Kolom `passed` di quiz_attempts sudah
     * dihitung dengan logika SAMA PERSIS seperti QuizController::submit()
     * ($score >= passing_score saat percobaan itu terjadi).
     */
    public function observationSequenceFor(int $userId, int $topicId): array
    {
        return QuizAttempt::where('user_id', $userId)
            ->where('topic_id', $topicId)
            ->orderBy('created_at')
            ->pluck('passed')
            ->all();
    }

    /**
     * Kumpulkan semua urutan observasi (satu per pasangan siswa-topik) yang
     * relevan untuk satu mata pelajaran (atau seluruh mapel kalau
     * $subjectId null) — dipakai perintah `php artisan bkt:fit`.
     */
    public function collectSequencesForSubject(?int $subjectId): array
    {
        $query = QuizAttempt::query()
            ->join('topics', 'quiz_attempts.topic_id', '=', 'topics.id');

        if ($subjectId !== null) {
            $query->where('topics.subject_id', $subjectId);
        }

        $rows = $query
            ->orderBy('quiz_attempts.user_id')
            ->orderBy('quiz_attempts.topic_id')
            ->orderBy('quiz_attempts.created_at')
            ->get(['quiz_attempts.user_id', 'quiz_attempts.topic_id', 'quiz_attempts.passed']);

        $sequences = [];
        foreach ($rows->groupBy(fn ($r) => $r->user_id . ':' . $r->topic_id) as $group) {
            $sequences[] = $group->pluck('passed')->values()->all();
        }

        return $sequences;
    }
}
