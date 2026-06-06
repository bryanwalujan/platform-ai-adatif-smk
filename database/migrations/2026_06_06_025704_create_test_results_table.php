<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['pre_test', 'post_test']);
            $table->decimal('score', 5, 2);
            $table->integer('correct_answers');
            $table->integer('total_questions');
            $table->integer('time_spent_minutes')->default(0);
            $table->timestamps();

            // Satu siswa hanya bisa punya satu pre_test dan satu post_test per topik
            $table->unique(['user_id', 'topic_id', 'type'], 'unique_test_per_topic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};