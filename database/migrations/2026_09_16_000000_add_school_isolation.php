<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users', 'subjects', 'topics', 'materials', 'quizzes', 'quiz_questions',
        'pbl_projects', 'lesson_plans', 'student_topic_mastery', 'learning_logs',
        'test_results', 'quiz_attempts', 'interaction_logs', 'discussions',
        'discussion_replies', 'app_notifications', 'bkt_parameters',
    ];

    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $id = DB::table('schools')->insertGetId([
            'name' => 'Sekolah Utama', 'code' => 'SEKOLAH-UTAMA', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($this->tables as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('school_id')->nullable()->index()->constrained('schools')->restrictOnDelete());
            DB::table($name)->update(['school_id' => $id]);
            Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('school_id')->nullable(false)->change());
        }
        // Some deployed databases already have this column, while fresh installs do not.
        if (! Schema::hasColumn('users', 'photo_path')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('photo_path')->nullable());
        }
    }

    public function down(): void
    {
        if (DB::table('schools')->count() > 1) {
            throw new RuntimeException('Rollback ditolak: beberapa sekolah sudah ada. Menghapus pembatas sekolah akan mencampur data.');
        }
        foreach (array_reverse($this->tables) as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('school_id'));
        }
        Schema::dropIfExists('schools');
    }
};
