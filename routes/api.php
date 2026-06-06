<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\PblProjectController;
use App\Http\Controllers\Api\LearningLogController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MasteryController;   
use App\Http\Controllers\Api\NotificationController; 
use App\Http\Controllers\Api\InteractionLogController; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (tidak perlu login)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes (wajib login via Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    Route::get('/me',     [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']); // BARU: logout invalidate token

    /*
    |--------------------------------------------------------------------------
    | Topik & Materi
    |--------------------------------------------------------------------------
    */

    Route::get('/topics',         [TopicController::class, 'index']);
    Route::get('/topics/{id}',    [TopicController::class, 'show']);
    Route::get('/materials/{id}', [MaterialController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Kuis
    |--------------------------------------------------------------------------
    */

    Route::get('/topics/{topicId}/quizzes',    [QuizController::class, 'getByTopic']);
    Route::get('/quizzes/{quizId}/questions',  [QuizController::class, 'getQuestions']);
    Route::post('/quizzes/{quizId}/submit',    [QuizController::class, 'submit']);

    // BARU: riwayat hasil kuis siswa
    Route::get('/quiz-results',                [QuizController::class, 'myResults']);

    /*
    |--------------------------------------------------------------------------
    | AI Adaptif & Mastery
    | BARU: endpoint /mastery dipisah dari /recommendations
    | Sebelumnya MasteryScreen reuse /recommendations yang strukturnya berbeda,
    | menyebabkan field mastery_level tidak terbaca (bug #2)
    |--------------------------------------------------------------------------
    */

    Route::get('/recommendations', [RecommendationController::class, 'index']);

    // BARU: endpoint mastery khusus untuk MasteryScreen (BarChart)
    // Response: [{ topic_title, mastery_level, attempts, last_accessed }, ...]
    Route::get('/mastery',         [MasteryController::class, 'index']);

    // BARU: trigger manual update mastery (opsional, biasanya dipanggil otomatis saat submit kuis)
    Route::post('/mastery/update', [MasteryController::class, 'update']);

    /*
    |--------------------------------------------------------------------------
    | Riwayat Belajar
    |--------------------------------------------------------------------------
    */

    Route::get('/learning-logs',  [LearningLogController::class, 'index']);

    // BARU: catat log belajar manual (saat siswa selesai baca materi, bukan hanya kuis)
    Route::post('/learning-logs', [LearningLogController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Proyek PBL
    |--------------------------------------------------------------------------
    */

    Route::get('/pbl-projects',           [PblProjectController::class, 'index']);
    Route::post('/pbl-projects',          [PblProjectController::class, 'store']);
    Route::get('/pbl-projects/rubric', [PblProjectController::class, 'getRubric']);

    // BARU: detail & hapus proyek milik sendiri
    Route::get('/pbl-projects/{id}',      [PblProjectController::class, 'show']);
    Route::delete('/pbl-projects/{id}',   [PblProjectController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Notifikasi
    | BARU: NotificationScreen saat ini masih hardcoded,
    | endpoint ini menyiapkan data real dari backend
    |--------------------------------------------------------------------------
    */

    // Hanya daftarkan route notifikasi jika controller tersedia
    if (class_exists(NotificationController::class)) {
        Route::get('/notifications',                     [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read',          [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all',           [NotificationController::class, 'markAllAsRead']);
    }

    /*
    |--------------------------------------------------------------------------
    | Profil Siswa
    | BARU: update profil (nama, foto) dari ProfileScreen
    |--------------------------------------------------------------------------
    */

    Route::put('/profile',        [AuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [AuthController::class, 'updatePhoto']);

    /*
    |--------------------------------------------------------------------------
    | Progress Report (untuk ProgressReportScreen — generate PDF)
    | BARU: satu endpoint yang mengembalikan semua data yang dibutuhkan PDF
    |--------------------------------------------------------------------------
    */

    Route::get('/progress-report', [RecommendationController::class, 'progressReport']);

    // BARU: endpoint untuk mencatat interaksi siswa (dipakai di TeacherAdaptiveScreen)
    Route::post('/interaction-logs',          [InteractionLogController::class, 'store']);
    Route::get('/interaction-logs/summary',   [InteractionLogController::class, 'summary']);

    /*
    |--------------------------------------------------------------------------
    | Guru Routes
    | Semua route guru dibungkus middleware 'role:guru' agar siswa
    | tidak bisa akses. Buat middleware ini di app/Http/Middleware/CheckRole.php
    |--------------------------------------------------------------------------
    */

    Route::prefix('guru')->group(function () {

        // Dashboard statistik guru
        Route::get('/dashboard',  [TeacherController::class, 'dashboard']);

        // Daftar siswa & progress individual
        Route::get('/students',                            [TeacherController::class, 'students']);
        Route::get('/students/{studentId}/progress',      [TeacherController::class, 'studentProgress']);

        // BARU: detail mastery per siswa untuk guru (sama seperti /mastery tapi bisa lihat siswa lain)
        Route::get('/students/{studentId}/mastery',       [TeacherController::class, 'studentMastery']);

        // Penilaian proyek PBL
        Route::get('/pending-projects',                   [TeacherController::class, 'pendingProjects']);
        Route::post('/projects/{projectId}/grade',        [TeacherController::class, 'gradeProject']);

        // BARU: semua proyek (tidak hanya pending)
        Route::get('/all-projects',                       [TeacherController::class, 'allProjects']);

        // BARU: kirim notifikasi ke siswa tertentu
        Route::post('/students/{studentId}/notify',       [TeacherController::class, 'notifyStudent']);

        // BARU: ringkasan interaksi siswa untuk guru (dipakai di TeacherAdaptiveScreen)
        Route::get('/students/{studentId}/interactions', [InteractionLogController::class, 'studentSummary']);

        /*
        |----------------------------------------------------------------------
        | Content Management (Manajemen Konten oleh Guru)
        |----------------------------------------------------------------------
        */

        Route::prefix('content')->group(function () {

            // Topik
            Route::get('/topics',                         [ContentController::class, 'getTopics']);
            Route::post('/topics',                        [ContentController::class, 'storeTopic']);

            // BARU: edit & hapus topik
            Route::put('/topics/{id}',                    [ContentController::class, 'updateTopic']);
            Route::delete('/topics/{id}',                 [ContentController::class, 'destroyTopic']);

            // Materi
            Route::post('/materials',                     [ContentController::class, 'storeMaterial']);

            // BARU: edit & hapus materi
            Route::put('/materials/{id}',                 [ContentController::class, 'updateMaterial']);
            Route::delete('/materials/{id}',              [ContentController::class, 'destroyMaterial']);

            // Kuis
            Route::get('/topics/{topicId}/quizzes',       [ContentController::class, 'getQuizzesByTopic']);
            Route::post('/quizzes',                       [ContentController::class, 'storeQuiz']);

            // BARU: edit & hapus kuis
            Route::put('/quizzes/{id}',                   [ContentController::class, 'updateQuiz']);
            Route::delete('/quizzes/{id}',                [ContentController::class, 'destroyQuiz']);

            // Pertanyaan kuis
            Route::post('/quizzes/{quizId}/questions',    [ContentController::class, 'storeQuizQuestion']);

            // BARU: edit & hapus pertanyaan
            Route::put('/questions/{id}',                 [ContentController::class, 'updateQuestion']);
            Route::delete('/questions/{id}',              [ContentController::class, 'destroyQuestion']);
        });
    });
});