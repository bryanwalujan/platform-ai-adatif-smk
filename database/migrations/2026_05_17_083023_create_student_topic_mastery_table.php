<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('student_topic_mastery', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('topic_id')->constrained()->onDelete('cascade');
        $table->decimal('mastery_level', 5, 2)->default(0); // 0 - 100
        $table->integer('attempts')->default(0);
        $table->timestamp('last_accessed')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'topic_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_topic_mastery');
    }
};
