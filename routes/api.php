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
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\AdminController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| File Storage Route (public, untuk serve file PBL)
| Diletakkan di luar auth agar bisa ditest di browser,
| keamanan dijaga oleh nama file yang random (UUID)
|--------------------------------------------------------------------------
*/

Route::get('/files/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);

    if (!file_exists($fullPath)) {
        return response()->json(['message' => 'File tidak ditemukan'], 404);
    }

    $mimeType = mime_content_type($fullPath);

    return response()->file($fullPath, [
        'Content-Type'                => $mimeType,
        'Access-Control-Allow-Origin' => '*',
        'Content-Disposition'         => 'inline; filename="' . basename($fullPath) . '"',
    ]);
})->where('path', '.*');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Mata Pelajaran (siswa) — lihat mapel yang diikuti, join pakai kode kelas, keluar
    Route::get('/subjects/my',        [SubjectController::class, 'mySubjects']);
    Route::post('/subjects/join',     [SubjectController::class, 'join']);
    Route::delete('/subjects/my/{id}',[SubjectController::class, 'leave']);

    // Topik & Materi
    Route::get('/topics',         [TopicController::class, 'index']);
    Route::get('/topics/{id}',    [TopicController::class, 'show']);
    Route::get('/materials/{id}', [MaterialController::class, 'show']);

    // Kuis
    Route::get('/topics/{topicId}/quizzes',   [QuizController::class, 'getByTopic']);
    Route::get('/quizzes/{quizId}/questions', [QuizController::class, 'getQuestions']);
    Route::post('/quizzes/{quizId}/submit',   [QuizController::class, 'submit']);
    Route::get('/quiz-results',               [QuizController::class, 'myResults']);

    // AI Adaptif & Mastery
    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::get('/mastery',         [MasteryController::class, 'index']);
    Route::post('/mastery/update', [MasteryController::class, 'update']);

    // Riwayat Belajar
    Route::get('/learning-logs',  [LearningLogController::class, 'index']);
    Route::post('/learning-logs', [LearningLogController::class, 'store']);

    // Proyek PBL
    Route::get('/pbl-projects',        [PblProjectController::class, 'index']);
    Route::post('/pbl-projects',       [PblProjectController::class, 'store']);
    Route::get('/pbl-projects/rubric', [PblProjectController::class, 'getRubric']);
    Route::get('/pbl-projects/{id}',   [PblProjectController::class, 'show']);
    Route::delete('/pbl-projects/{id}',[PblProjectController::class, 'destroy']);

    // Notifikasi
    Route::get('/notifications',              [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllAsRead']);

    // Export siswa
    Route::get('/export/my-progress', [ExportController::class, 'myProgress']);

    // Profil
    Route::put('/profile',        [AuthController::class, 'updateProfile']);
    Route::post('/profile/photo', [AuthController::class, 'updatePhoto']);

    // Progress Report
    Route::get('/progress-report', [RecommendationController::class, 'progressReport']);

    // Interaction Logs
    Route::post('/interaction-logs',        [InteractionLogController::class, 'store']);
    Route::get('/interaction-logs/summary', [InteractionLogController::class, 'summary']);

    // Diskusi
    Route::get('/topics/{topicId}/discussions',              [DiscussionController::class, 'index']);
    Route::post('/topics/{topicId}/discussions',             [DiscussionController::class, 'store']);
    Route::get('/discussions/{id}',                          [DiscussionController::class, 'show']);
    Route::post('/discussions/{id}/replies',                 [DiscussionController::class, 'reply']);
    Route::post('/discussions/{id}/resolve',                 [DiscussionController::class, 'resolve']);
    Route::post('/discussions/{id}/replies/{replyId}/best',  [DiscussionController::class, 'markBestAnswer']);

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        Route::get('/teachers/pending',        [AdminController::class, 'pendingTeachers']);
        Route::post('/teachers/{id}/approve',  [AdminController::class, 'approveTeacher']);
        Route::post('/teachers/{id}/reject',   [AdminController::class, 'rejectTeacher']);

        Route::get('/users',              [AdminController::class, 'users']);
        Route::get('/users/{id}',         [AdminController::class, 'userDetail']);
        Route::put('/users/{id}/status',  [AdminController::class, 'updateUserStatus']);

        Route::get('/subjects',                              [AdminController::class, 'subjects']);
        Route::get('/subjects/{id}',                         [AdminController::class, 'subjectDetail']);
        Route::post('/subjects/{id}/teachers',                [AdminController::class, 'addTeacher']);
        Route::delete('/subjects/{id}/teachers/{userId}',     [AdminController::class, 'removeTeacher']);
        Route::delete('/subjects/{id}',                       [AdminController::class, 'deactivateSubject']);
    });

    /*
    |--------------------------------------------------------------------------
    | Guru Routes
    |--------------------------------------------------------------------------
    */

    // 'approved' memblokir akun guru yang statusnya masih pending/rejected —
    // lihat App\Http\Middleware\EnsureApproved.
    Route::middleware(['role:guru', 'approved'])->prefix('guru')->group(function () {

        Route::get('/dashboard',                         [TeacherController::class, 'dashboard']);
        Route::get('/students',                          [TeacherController::class, 'students']);
        Route::get('/students/search',                   [SubjectController::class, 'searchStudents']);
        Route::get('/students/{studentId}/progress',     [TeacherController::class, 'studentProgress']);
        Route::get('/students/{studentId}/mastery',      [TeacherController::class, 'studentMastery']);
        Route::get('/students/{studentId}/interactions', [InteractionLogController::class, 'studentSummary']);
        Route::get('/pending-projects',                  [TeacherController::class, 'pendingProjects']);
        Route::post('/projects/{projectId}/grade',       [TeacherController::class, 'gradeProject']);
        Route::get('/all-projects',                      [TeacherController::class, 'allProjects']);
        Route::post('/students/{studentId}/notify',      [TeacherController::class, 'notifyStudent']);
        Route::post('/discussions/{id}/pin',             [DiscussionController::class, 'pin']);
        Route::get('/export/students',                   [ExportController::class, 'allStudents']);

        // Mata Pelajaran — buat mapel, kode kelas, kelola siswa di mapelnya
        Route::get('/subjects',                                  [SubjectController::class, 'index']);
        Route::post('/subjects',                                 [SubjectController::class, 'store']);
        Route::get('/subjects/{id}',                             [SubjectController::class, 'show']);
        Route::put('/subjects/{id}',                             [SubjectController::class, 'update']);
        Route::post('/subjects/{id}/join-code/regenerate',       [SubjectController::class, 'regenerateJoinCode']);
        Route::get('/subjects/{id}/students',                    [SubjectController::class, 'students']);
        Route::post('/subjects/{id}/students',                   [SubjectController::class, 'addStudent']);
        Route::delete('/subjects/{id}/students/{studentId}',     [SubjectController::class, 'removeStudent']);

        // Legacy — dipertahankan untuk klien Flutter lama yang belum tahu
        // konsep mata pelajaran. subject_id di-resolve otomatis (lihat
        // SubjectAccessService::resolveSubjectId) ke mapel tertua milik guru.
        Route::prefix('content')->group(function () {
            Route::get('/topics',                       [ContentController::class, 'getTopics']);
            Route::post('/topics',                      [ContentController::class, 'storeTopic']);
            Route::put('/topics/{id}',                  [ContentController::class, 'updateTopic']);
            Route::delete('/topics/{id}',               [ContentController::class, 'destroyTopic']);
            Route::post('/materials',                   [ContentController::class, 'storeMaterial']);
            Route::put('/materials/{id}',               [ContentController::class, 'updateMaterial']);
            Route::delete('/materials/{id}',            [ContentController::class, 'destroyMaterial']);
            Route::get('/topics/{topicId}/quizzes',     [ContentController::class, 'getQuizzesByTopic']);
            Route::post('/quizzes',                     [ContentController::class, 'storeQuiz']);
            Route::put('/quizzes/{id}',                 [ContentController::class, 'updateQuiz']);
            Route::delete('/quizzes/{id}',              [ContentController::class, 'destroyQuiz']);
            Route::post('/quizzes/{quizId}/questions',  [ContentController::class, 'storeQuizQuestion']);
            Route::put('/questions/{id}',               [ContentController::class, 'updateQuestion']);
            Route::delete('/questions/{id}',            [ContentController::class, 'destroyQuestion']);
        });

        // Baru — subject_id eksplisit dari URL, dipakai Flutter versi
        // mendatang yang sudah sadar mata pelajaran. Controller method-nya
        // SAMA PERSIS dengan grup 'content' di atas (subject_id jadi
        // parameter tambahan, di-resolve dari route alih-alih fallback).
        Route::prefix('subjects/{subjectId}/content')->group(function () {
            Route::get('/topics',                       [ContentController::class, 'getTopics']);
            Route::post('/topics',                      [ContentController::class, 'storeTopic']);
            Route::put('/topics/{id}',                  [ContentController::class, 'updateTopic']);
            Route::delete('/topics/{id}',               [ContentController::class, 'destroyTopic']);
            Route::post('/materials',                   [ContentController::class, 'storeMaterial']);
            Route::put('/materials/{id}',               [ContentController::class, 'updateMaterial']);
            Route::delete('/materials/{id}',            [ContentController::class, 'destroyMaterial']);
            Route::get('/topics/{topicId}/quizzes',     [ContentController::class, 'getQuizzesByTopic']);
            Route::post('/quizzes',                     [ContentController::class, 'storeQuiz']);
            Route::put('/quizzes/{id}',                 [ContentController::class, 'updateQuiz']);
            Route::delete('/quizzes/{id}',              [ContentController::class, 'destroyQuiz']);
            Route::post('/quizzes/{quizId}/questions',  [ContentController::class, 'storeQuizQuestion']);
            Route::put('/questions/{id}',               [ContentController::class, 'updateQuestion']);
            Route::delete('/questions/{id}',            [ContentController::class, 'destroyQuestion']);
        });
    });
});