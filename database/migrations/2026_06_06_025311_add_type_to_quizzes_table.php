<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            // type: 'regular' | 'pre_test' | 'post_test'
            // default 'regular' agar semua kuis lama tidak terpengaruh
            $table->enum('type', ['regular', 'pre_test', 'post_test'])
                  ->default('regular')
                  ->after('title');
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            // Bobot soal — untuk pre/post test bisa diberi bobot berbeda
            $table->integer('point')->default(1)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('point');
        });
    }
};