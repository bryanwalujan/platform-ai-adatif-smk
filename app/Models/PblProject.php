<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class PblProject extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'user_id',
        'topic_id',
        'subject_id',
        'title',
        'description',
        'level',
        'status',
        'file_path',
        'file_name',
        'file_type',
        'attachments',
        'score',
        'rubric_scores',
        'rubric_feedback',
        'feedback',
        'graded_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'score' => 'float',
        'rubric_scores' => 'array',
        'rubric_feedback' => 'array',
        'graded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function formattedAttachments(): array
    {
        $files = $this->attachments ?? [];
        if (! $files && $this->file_path) {
            return [[
                'name' => $this->file_name,
                'mime_type' => $this->file_type,
                'size' => null,
                'url' => request()->is('guru/*') ? route('school.files', ['path' => $this->file_path]) : url('/api/files/'.$this->file_path),
            ]];
        }

        return array_map(fn ($file, $index) => [
            'name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'size' => $file['size'],
            'url' => route(request()->is('guru/*') ? 'school.pbl.attachment' : 'pbl.attachment', ['project' => $this->id, 'index' => $index]),
        ], $files, array_keys($files));
    }

    // Rubrik penilaian PBL lintas mata pelajaran — 4 kriteria dengan bobot
    public static function rubricCriteria(): array
    {
        return [
            'kreativitas' => [
                'label' => 'Pemecahan Masalah & Inisiatif',
                'weight' => 25, // bobot 25%
                'description' => 'Kesesuaian solusi dengan masalah, inisiatif, penalaran, dan orisinalitas pendekatan',
                'indicators' => [
                    100 => 'Solusi sangat relevan, mandiri, dan didukung penalaran kuat',
                    80 => 'Solusi relevan dengan inisiatif dan penalaran yang baik',
                    60 => 'Solusi cukup relevan, penalaran dan inisiatif perlu dikembangkan',
                    40 => 'Solusi belum menjawab masalah dan minim penalaran mandiri',
                ],
            ],
            'teknis' => [
                'label' => 'Ketepatan Metode & Hasil',
                'weight' => 35, // bobot 35%
                'description' => 'Ketepatan prosedur, perhitungan atau teknik sesuai mata pelajaran; ketelitian hasil dan bukti pendukung',
                'indicators' => [
                    100 => 'Metode tepat, hasil akurat, dan bukti pendukung lengkap',
                    80 => 'Metode dan hasil sebagian besar tepat',
                    60 => 'Metode cukup tepat tetapi masih ada kesalahan hasil',
                    40 => 'Metode belum tepat dan hasil belum didukung bukti memadai',
                ],
            ],
            'konsep' => [
                'label' => 'Pemahaman Materi',
                'weight' => 25, // bobot 25%
                'description' => 'Ketepatan konsep, argumentasi, dan penerapan materi pada tugas yang diberikan',
                'indicators' => [
                    100 => 'Konsep dipahami dan diterapkan dengan sangat baik',
                    80 => 'Konsep dipahami dan sebagian besar diterapkan',
                    60 => 'Konsep cukup dipahami namun penerapan belum optimal',
                    40 => 'Konsep kurang dipahami, banyak kesalahan penerapan',
                ],
            ],
            'presentasi' => [
                'label' => 'Komunikasi & Dokumentasi',
                'weight' => 15, // bobot 15%
                'description' => 'Kejelasan penyampaian, susunan laporan/karya, sumber rujukan, dan kelengkapan bukti proses',
                'indicators' => [
                    100 => 'Dokumentasi sangat lengkap dan rapi',
                    80 => 'Dokumentasi lengkap',
                    60 => 'Dokumentasi cukup lengkap',
                    40 => 'Dokumentasi kurang lengkap',
                ],
            ],
        ];
    }

    // Hitung total skor dari rubrik (weighted average)
    public function calculateWeightedScore(): float
    {
        if (! $this->rubric_scores) {
            return 0;
        }

        $criteria = self::rubricCriteria();
        $total = 0;

        foreach ($criteria as $key => $c) {
            $score = $this->rubric_scores[$key] ?? 0;
            $total += ($score * $c['weight']) / 100;
        }

        return round($total, 2);
    }
}
