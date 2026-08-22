<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminPanelController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\GuruAuthController;
use App\Http\Controllers\Web\GuruPanelController;

Route::prefix('guru')->group(function () {

    // Sama seperti catatan di grup admin: nama 'guru.login' dipakai closure
    // redirectGuestsTo() di bootstrap/app.php untuk guest yang kena redirect
    // dari /guru/*. Lihat instruksi di bawah.
    Route::get('/login',  [GuruAuthController::class, 'showLogin'])->name('guru.login');
    Route::post('/login', [GuruAuthController::class, 'login'])->name('guru.login.submit');

    Route::name('guru.')->middleware(['auth', 'role:guru', 'approved'])->group(function () {
        Route::post('/logout', [GuruAuthController::class, 'logout'])->name('logout');

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
});

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Panel Web Admin (sementara)
|--------------------------------------------------------------------------
| Session-based (guard 'web'), terpisah total dari API Sanctum yang dipakai
| Flutter (routes/api.php). Dibuat sambil menunggu tampilan admin Flutter
| dibangun — akan disusul/digantikan nanti, bukan pengganti permanen.
*/

Route::prefix('admin')->group(function () {

    // Sengaja DI LUAR grup ->name('admin.') di bawah — kalau nama route ini
    // jadi 'admin.login', redirectGuestsTo() di bootstrap/app.php (yang
    // memanggil route('login')) gagal resolve dan bikin 500 untuk semua
    // guest yang kena redirect. Nama 'login'/'login.submit' harus persis.
    Route::get('/login',  [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');

    Route::name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

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
});
