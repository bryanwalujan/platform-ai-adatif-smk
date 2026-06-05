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
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    // Siswa Routes
    Route::get('/topics', [TopicController::class, 'index']);
    Route::get('/topics/{id}', [TopicController::class, 'show']);
    Route::get('/materials/{id}', [MaterialController::class, 'show']);

    Route::get('/topics/{topicId}/quizzes', [QuizController::class, 'getByTopic']);
    Route::get('/quizzes/{quizId}/questions', [QuizController::class, 'getQuestions']);
    Route::post('/quizzes/{quizId}/submit', [QuizController::class, 'submit']);

    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::post('/pbl-projects', [PblProjectController::class, 'store']);
    Route::get('/pbl-projects', [PblProjectController::class, 'index']);
    Route::get('/learning-logs', [LearningLogController::class, 'index']);

    // Guru Routes
    Route::prefix('guru')->group(function () {
        Route::get('/dashboard', [TeacherController::class, 'dashboard']);
        Route::get('/students', [TeacherController::class, 'students']);
        Route::get('/students/{studentId}/progress', [TeacherController::class, 'studentProgress']);
        Route::get('/pending-projects', [TeacherController::class, 'pendingProjects']);
        Route::post('/projects/{projectId}/grade', [TeacherController::class, 'gradeProject']);

        // Content Management
        Route::prefix('content')->group(function () {
            Route::get('/topics', [ContentController::class, 'getTopics']);
            Route::post('/topics', [ContentController::class, 'storeTopic']);
            Route::post('/materials', [ContentController::class, 'storeMaterial']);
            Route::post('/quizzes', [ContentController::class, 'storeQuiz']);
            Route::get('/topics/{topicId}/quizzes', [ContentController::class, 'getQuizzesByTopic']);
            Route::post('/quizzes/{quizId}/questions', [ContentController::class, 'storeQuizQuestion']);
        });
    });
});