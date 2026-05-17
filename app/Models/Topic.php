<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'order'];

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function studentMasteries()
    {
        return $this->hasMany(StudentTopicMastery::class);
    }
}