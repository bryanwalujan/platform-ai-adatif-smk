<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot enrollment siswa ke mata pelajaran. `enrollment_type` membedakan
     * siswa yang gabung sendiri lewat kode kelas ('self_joined') dari yang
     * di-assign manual oleh guru/admin ('assigned').
     */
    public function up(): void
    {
        Schema::create('subject_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('enrollment_type', ['self_joined', 'assigned'])->default('self_joined');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_student');
    }
};
