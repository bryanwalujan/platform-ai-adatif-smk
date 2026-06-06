<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_id')->nullable()
                  ->constrained()->onDelete('cascade');

            // Tipe interaksi:
            // 'open_topic'    — siswa membuka halaman topik
            // 'open_material' — siswa membuka materi
            // 'play_video'    — siswa memutar video
            // 'finish_read'   — siswa tap tombol Selesai Membaca
            // 'repeat_material' — siswa membuka materi yang sama lebih dari 1x
            $table->enum('action', [
                'open_topic',
                'open_material',
                'play_video',
                'finish_read',
                'repeat_material',
            ]);

            $table->integer('duration_seconds')->default(0); // lama interaksi
            $table->integer('open_count')->default(1);       // berapa kali dibuka
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_logs');
    }
};