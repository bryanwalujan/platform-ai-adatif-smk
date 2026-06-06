<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Tipe notifikasi:
            // 'recommendation' — dari AI Adaptif
            // 'feedback'       — guru sudah nilai proyek PBL
            // 'reminder'       — pengingat belajar
            // 'achievement'    — siswa capai mastery tinggi
            $table->enum('type', [
                'recommendation',
                'feedback',
                'reminder',
                'achievement',
            ]);

            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);

            // Data tambahan opsional (misal: topic_id, project_id)
            // disimpan sebagai JSON agar fleksibel
            $table->json('data')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};