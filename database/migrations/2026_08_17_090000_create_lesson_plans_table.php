<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RPP (Rencana Pelaksanaan Pembelajaran) per mata pelajaran — rencana
     * materi per pertemuan sepanjang semester, dibuat guru pengampu,
     * cuma kelihatan buat guru & siswa mapel itu sendiri (di-scope lewat
     * subject_id, pola yang sama seperti topics/materials/dst).
     */
    public function up(): void
    {
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Opsional — kalau materi pertemuan ini sudah benar-benar dibuat
            // sebagai Topic di sistem, guru bisa hubungkan langsung.
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('meeting_number'); // "Pertemuan ke-N"
            $table->string('title');
            $table->text('learning_objective')->nullable(); // Tujuan Pembelajaran
            $table->text('description')->nullable(); // Materi/kegiatan/catatan tambahan
            $table->date('scheduled_date')->nullable(); // Rencana tanggal pelaksanaan
            $table->boolean('is_completed')->default(false); // Buat tracking guru

            $table->timestamps();

            $table->unique(['subject_id', 'meeting_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
