<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parameter Bayesian Knowledge Tracing hasil fitting (lihat migration untuk
 * penjelasan tiap kolom). subject_id null = parameter global/fallback.
 */
class BktParameter extends Model
{
    protected $fillable = [
        'subject_id',
        'p_l0',
        'p_t',
        'p_s',
        'p_g',
        'fitted_from_sequences',
        'log_likelihood',
    ];

    protected $casts = [
        'p_l0' => 'float',
        'p_t' => 'float',
        'p_s' => 'float',
        'p_g' => 'float',
        'log_likelihood' => 'float',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function toParamArray(): array
    {
        return [
            'p_l0' => $this->p_l0,
            'p_t' => $this->p_t,
            'p_s' => $this->p_s,
            'p_g' => $this->p_g,
        ];
    }
}
