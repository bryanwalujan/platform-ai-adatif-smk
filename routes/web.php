<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminPanelController;
use Illuminate\Support\Facades\Route;

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
