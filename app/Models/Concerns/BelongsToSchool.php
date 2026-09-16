<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait BelongsToSchool
{
    public function initializeBelongsToSchool(): void
    {
        $this->mergeFillable(['school_id']);
    }

    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope('school', function (Builder $query) {
            $id = app(SchoolContext::class)->id;
            if ($id !== null) {
                $query->where($query->getModel()->qualifyColumn('school_id'), $id);
            }
        });

        static::saving(function (Model $model) {
            $context = app(SchoolContext::class)->id;
            if ($model->exists && $model->isDirty('school_id')) {
                throw ValidationException::withMessages(['school_id' => 'Sekolah akun/data tidak dapat dipindahkan.']);
            }
            $references = [
                'user_id' => 'users', 'created_by' => 'users', 'subject_id' => 'subjects',
                'topic_id' => 'topics', 'quiz_id' => 'quizzes', 'material_id' => 'materials',
                'discussion_id' => 'discussions',
            ];
            $schools = [];
            foreach ($references as $column => $table) {
                if ($model->getAttribute($column) !== null) {
                    $schoolId = DB::table($table)->where('id', $model->getAttribute($column))->value('school_id');
                    if (! $schoolId) {
                        throw ValidationException::withMessages([$column => 'Data rujukan tidak tersedia.']);
                    }
                    $schools[] = (int) $schoolId;
                }
            }
            // Explicit school is required outside a request except legacy fixtures/seeders.
            $id = $model->getAttribute('school_id') ?? $context ?? ($schools[0] ?? null);
            if (! $id && app()->runningInConsole()) {
                $id = School::where('code', 'SEKOLAH-UTAMA')->value('id');
            }
            if (! $id || ($context !== null && (int) $id !== $context) ||
                collect($schools)->contains(fn ($school) => $school !== (int) $id)) {
                throw ValidationException::withMessages(['school_id' => 'Data harus berasal dari sekolah yang sama.']);
            }
            $model->setAttribute('school_id', $id);
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
