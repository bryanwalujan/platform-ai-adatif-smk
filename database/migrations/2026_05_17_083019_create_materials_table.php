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
    Schema::create('materials', function (Blueprint $table) {
        $table->id();
        $table->foreignId('topic_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('content');
        $table->string('video_url')->nullable();
        $table->integer('duration_minutes')->default(0);
        $table->integer('order')->default(1);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
