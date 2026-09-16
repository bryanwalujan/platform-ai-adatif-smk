<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class School extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function newCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
