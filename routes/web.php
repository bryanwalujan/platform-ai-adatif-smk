<?php

use App\Http\Controllers\Web\AdminPanelController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\GuruPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Login Terpadu — satu form untuk admin & guru
|--------------------------------------------------------------------------
| Redirect tujuan (admin.dashboard / guru.dashboard) ditentukan dari role
| user SETELAH kredensial tervalidasi — lihat AuthController::login().
| Nama route 'login'/'login.submit' dipakai closure redirectGuestsTo() di
| bootstrap/app.php, dan Route::has('login') di welcome.blade.php — jangan
| ganti nama route ini.
*/
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

/*
|--------------------------------------------------------------------------
| Panel Web Admin (sementara)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [AdminPanelController::class, 'dashboard'])->name('dashboard');

    Route::get('/teachers/pending',       [AdminPanelController::class, 'pendingTeachers'])->name('teachers.pending');
    Route::post('/teachers/{id}/approve', [AdminPanelController::class, 'approveTeacher'])->name('teachers.approve');
    Route::post('/teachers/{id}/reject',  [AdminPanelController::class, 'rejectTeacher'])->name('teachers.reject');

    Route::get('/users', [AdminPanelController::class, 'users'])->name('users.index');

    Route::get('/subjects',                              [AdminPanelController::class, 'subjects'])->name('subjects.index');
    Route::get('/subjects/{id}',                          [AdminPanelController::class, 'subjectDetail'])->name('subjects.show');
    Route::post('/subjects/{id}/teachers',                [AdminPanelController::class, 'addTeacher'])->name('subjects.teachers.add');
    Route::delete('/subjects/{id}/teachers/{userId}',     [AdminPanelController::class, 'removeTeacher'])->name('subjects.teachers.remove');
    Route::delete('/subjects/{id}',                       [AdminPanelController::class, 'deactivateSubject'])->name('subjects.deactivate');
});

/*
|--------------------------------------------------------------------------
| Panel Web Guru
|--------------------------------------------------------------------------
*/
Route::prefix('guru')->name('guru.')->middleware(['auth', 'role:guru', 'approved'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [GuruPanelController::class, 'dashboard'])->name('dashboard');

    Route::get('/students',                       [GuruPanelController::class, 'students'])->name('students.index');
    Route::get('/students/{studentId}',            [GuruPanelController::class, 'studentShow'])->name('students.show');
    Route::get('/students/{studentId}/notify',      [GuruPanelController::class, 'notifyStudentForm'])->name('students.notify.form');
    Route::post('/students/{studentId}/notify',     [GuruPanelController::class, 'notifyStudent'])->name('students.notify');

    Route::get('/projects/pending',                 [GuruPanelController::class, 'pendingProjects'])->name('projects.pending');
    Route::get('/projects',                         [GuruPanelController::class, 'allProjects'])->name('projects.index');
    Route::get('/projects/{projectId}/grade',       [GuruPanelController::class, 'gradeProjectForm'])->name('projects.grade.form');
    Route::post('/projects/{projectId}/grade',      [GuruPanelController::class, 'gradeProject'])->name('projects.grade');

    Route::get('/subjects', [GuruPanelController::class, 'subjects'])->name('subjects.index');
});