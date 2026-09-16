<?php

namespace App\Console\Commands;

use App\Models\BktParameter;
use App\Models\School;
use App\Models\Subject;
use App\Services\BayesianKnowledgeTracingService;
use App\Support\SchoolContext;
use Illuminate\Console\Command;

/**
 * "Melatih" model Bayesian Knowledge Tracing — mengestimasi parameter
 * (p_l0, p_t, p_s, p_g) dari riwayat benar/salah kuis siswa yang
 * sesungguhnya, lalu menyimpannya ke tabel bkt_parameters. Inilah langkah
 * "pembelajaran mesin"-nya: parameter tidak ditentukan manual oleh
 * developer, tapi diestimasi lewat pencocokan (fitting) terhadap data.
 *
 *   php artisan bkt:fit                  -> fit parameter GLOBAL (semua mapel)
 *   php artisan bkt:fit --subject=3      -> fit khusus mapel id 3
 *   php artisan bkt:fit --all-subjects   -> fit satu per satu untuk tiap mapel
 *                                            YANG datanya cukup, plus global
 */
class FitBktParameters extends Command
{
    protected $signature = 'bkt:fit
        {--school= : Kode sekolah (kosongkan untuk memproses tiap sekolah secara terpisah)}
        {--subject= : ID mata pelajaran spesifik (kosongkan untuk parameter global)}
        {--all-subjects : Fit parameter untuk tiap mata pelajaran secara terpisah, plus global}
        {--min-sequences=5 : Jumlah minimum urutan observasi supaya fitting dianggap layak dipakai}';

    protected $description = 'Estimasi parameter Bayesian Knowledge Tracing dari riwayat kuis siswa';

    public function handle(BayesianKnowledgeTracingService $bkt): int
    {
        $schools = School::where('is_active', true)
            ->when($this->option('school'), fn ($q) => $q->where('code', strtoupper($this->option('school'))))->get();
        if ($schools->isEmpty()) {
            $this->error('Sekolah tidak ditemukan.');

            return self::FAILURE;
        }
        foreach ($schools as $school) {
            app(SchoolContext::class)->run($school->id, function () use ($bkt, $school) {
                $this->info('Sekolah: '.$school->name);
                $min = max(1, (int) $this->option('min-sequences'));
                if ($this->option('subject')) {
                    $subject = Subject::find($this->option('subject'));
                    if ($subject) {
                        $this->fitAndSave($bkt, $subject->id, $min, $subject->name);
                    }

                    return;
                }
                $this->fitAndSave($bkt, null, $min, 'Fallback sekolah');
                if ($this->option('all-subjects')) {
                    foreach (Subject::all() as $subject) {
                        $this->fitAndSave($bkt, $subject->id, $min, $subject->name);
                    }
                }
            });
        }

        return self::SUCCESS;
    }

    private function fitAndSave(
        BayesianKnowledgeTracingService $bkt,
        ?int $subjectId,
        int $minSequences,
        ?string $label = null
    ): void {
        $label = $label ?? ($subjectId ? "mapel #{$subjectId}" : 'GLOBAL (semua mapel)');

        $sequences = $bkt->collectSequencesForSubject($subjectId);

        if (count($sequences) < $minSequences) {
            $this->warn(
                "[{$label}] Cuma ada ".count($sequences)." urutan observasi (butuh minimal {$minSequences}). ".
                'Dilewati — belum cukup data historis untuk fitting yang layak dipakai.'
            );

            return;
        }

        $result = $bkt->fitParameters($sequences);

        BktParameter::updateOrCreate(
            ['subject_id' => $subjectId],
            [
                'p_l0' => $result['params']['p_l0'],
                'p_t' => $result['params']['p_t'],
                'p_s' => $result['params']['p_s'],
                'p_g' => $result['params']['p_g'],
                'fitted_from_sequences' => $result['sequences_used'],
                'log_likelihood' => $result['log_likelihood'],
            ]
        );

        $p = $result['params'];
        $this->info(sprintf(
            '[%s] Fit dari %d urutan | P(L0)=%.2f P(T)=%.2f P(S)=%.2f P(G)=%.2f | log-likelihood=%.2f',
            $label,
            $result['sequences_used'],
            $p['p_l0'],
            $p['p_t'],
            $p['p_s'],
            $p['p_g'],
            $result['log_likelihood']
        ));
    }
}
