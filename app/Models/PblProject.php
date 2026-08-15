<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PblProject extends Model
{
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
        'score',
        'rubric_scores',
        'rubric_feedback',
        'feedback',
        'graded_at',
    ];

    protected $casts = [
        'rubric_scores'   => 'array',
        'rubric_feedback' => 'array',
        'graded_at'       => 'datetime',
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function topic()   { return $this->belongsTo(Topic::class); }
    public function subject() { return $this->belongsTo(Subject::class); }

    // Rubrik penilaian PBL animasi SMK — 4 kriteria dengan bobot
    public static function rubricCriteria(): array
    {
        return [
            'kreativitas' => [
                'label'       => 'Kreativitas & Orisinalitas',
                'weight'      => 25, // bobot 25%
                'description' => 'Ide original, visual menarik, konsep unik',
                'indicators'  => [
                    100 => 'Sangat kreatif, ide sangat original dan unik',
                    80  => 'Kreatif, ada beberapa elemen original',
                    60  => 'Cukup kreatif, masih banyak referensi langsung',
                    40  => 'Kurang kreatif, banyak meniru referensi',
                ],
            ],
            'teknis' => [
                'label'       => 'Kualitas Teknis Animasi',
                'weight'      => 35, // bobot 35%
                'description' => 'Prinsip animasi, timing, gerakan, rendering',
                'indicators'  => [
                    100 => 'Teknis sangat baik, prinsip animasi diterapkan sempurna',
                    80  => 'Teknis baik, sebagian besar prinsip diterapkan',
                    60  => 'Teknis cukup, beberapa prinsip belum diterapkan',
                    40  => 'Teknis kurang, banyak kesalahan animasi',
                ],
            ],
            'konsep' => [
                'label'       => 'Pemahaman Konsep',
                'weight'      => 25, // bobot 25%
                'description' => 'Pemahaman teori animasi yang diterapkan di proyek',
                'indicators'  => [
                    100 => 'Konsep dipahami dan diterapkan dengan sangat baik',
                    80  => 'Konsep dipahami dan sebagian besar diterapkan',
                    60  => 'Konsep cukup dipahami namun penerapan belum optimal',
                    40  => 'Konsep kurang dipahami, banyak kesalahan penerapan',
                ],
            ],
            'presentasi' => [
                'label'       => 'Presentasi & Dokumentasi',
                'weight'      => 15, // bobot 15%
                'description' => 'Kelengkapan dokumen, kerapian, penjelasan proyek',
                'indicators'  => [
                    100 => 'Dokumentasi sangat lengkap dan rapi',
                    80  => 'Dokumentasi lengkap',
                    60  => 'Dokumentasi cukup lengkap',
                    40  => 'Dokumentasi kurang lengkap',
                ],
            ],
        ];
    }

    // Hitung total skor dari rubrik (weighted average)
    public function calculateWeightedScore(): float
    {
        if (!$this->rubric_scores) return 0;

        $criteria = self::rubricCriteria();
        $total    = 0;

        foreach ($criteria as $key => $c) {
            $score  = $this->rubric_scores[$key] ?? 0;
            $total += ($score * $c['weight']) / 100;
        }

        return round($total, 2);
    }
}