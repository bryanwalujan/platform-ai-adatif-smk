<?php

use App\Http\Controllers\Web\AdminPanelController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\GuruPanelController;
use App\Http\Controllers\Web\GuruSubjectController;
use App\Http\Controllers\Web\GuruLessonPlanController;
use App\Http\Controllers\Web\GuruContentController;
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

    Route::put('/users/{id}/status', [AdminPanelController::class, 'updateUserStatus'])->name('users.status.update');
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
    Route::get('/students/search', [GuruSubjectController::class, 'searchStudents'])->name('students.search');

    Route::get('/projects/pending',                 [GuruPanelController::class, 'pendingProjects'])->name('projects.pending');
    Route::get('/projects',                         [GuruPanelController::class, 'allProjects'])->name('projects.index');
    Route::get('/projects/{projectId}/grade',       [GuruPanelController::class, 'gradeProjectForm'])->name('projects.grade.form');
    Route::post('/projects/{projectId}/grade',      [GuruPanelController::class, 'gradeProject'])->name('projects.grade');

    /*
    |----------------------------------------------------------------------
    | Mata Pelajaran
    |----------------------------------------------------------------------
    */
    Route::prefix('subjects')->name('subjects.')->group(function () {
        Route::get('/',                          [GuruSubjectController::class, 'index'])->name('index');
        Route::get('/create',                    [GuruSubjectController::class, 'create'])->name('create');
        Route::post('/',                         [GuruSubjectController::class, 'store'])->name('store');
        Route::get('/{id}',                      [GuruSubjectController::class, 'show'])->name('show');
        Route::get('/{id}/edit',                 [GuruSubjectController::class, 'edit'])->name('edit');
        Route::put('/{id}',                      [GuruSubjectController::class, 'update'])->name('update');
        Route::post('/{id}/join-code/regenerate',[GuruSubjectController::class, 'regenerateJoinCode'])->name('join-code.regenerate');
        Route::post('/{id}/students',            [GuruSubjectController::class, 'addStudent'])->name('students.add');
        Route::delete('/{id}/students/{studentId}', [GuruSubjectController::class, 'removeStudent'])->name('students.remove');
    });

    /*
    |----------------------------------------------------------------------
    | RPP — index/create/store per-mapel, show/edit/update/destroy per-RPP.
    | SENGAJA di luar grup 'subjects.' supaya seluruh route RPP konsisten
    | bernama guru.lesson-plans.* (bukan guru.subjects.lesson-plans.*).
    |----------------------------------------------------------------------
    */
    Route::prefix('lesson-plans')->name('lesson-plans.')->group(function () {
        Route::get('/subject/{subjectId}',         [GuruLessonPlanController::class, 'index'])->name('index');
        Route::get('/subject/{subjectId}/create',  [GuruLessonPlanController::class, 'create'])->name('create');
        Route::post('/subject/{subjectId}',        [GuruLessonPlanController::class, 'store'])->name('store');

        Route::get('/{id}',                    [GuruLessonPlanController::class, 'show'])->name('show');
        Route::get('/{id}/edit',               [GuruLessonPlanController::class, 'edit'])->name('edit');
        Route::put('/{id}',                    [GuruLessonPlanController::class, 'update'])->name('update');
        Route::delete('/{id}',                 [GuruLessonPlanController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-complete',   [GuruLessonPlanController::class, 'toggleComplete'])->name('toggle-complete');
    });

    /*
    |----------------------------------------------------------------------
    | Konten: topik/materi/kuis/soal. SENGAJA di luar grup 'subjects.' —
    | alasan sama seperti RPP di atas.
    |----------------------------------------------------------------------
    */
    Route::prefix('content')->name('content.')->group(function () {
        Route::get('/subject/{subjectId}/topics',        [GuruContentController::class, 'topics'])->name('topics');
        Route::get('/subject/{subjectId}/topics/create',  [GuruContentController::class, 'createTopic'])->name('topics.create');
        Route::post('/subject/{subjectId}/topics',        [GuruContentController::class, 'storeTopic'])->name('topics.store');

        Route::get('/topics/{id}',        [GuruContentController::class, 'showTopic'])->name('topics.show');
        Route::get('/topics/{id}/edit',   [GuruContentController::class, 'editTopic'])->name('topics.edit');
        Route::put('/topics/{id}',        [GuruContentController::class, 'updateTopic'])->name('topics.update');
        Route::delete('/topics/{id}',     [GuruContentController::class, 'destroyTopic'])->name('topics.destroy');

        Route::get('/topics/{topicId}/materials/create', [GuruContentController::class, 'createMaterial'])->name('materials.create');
        Route::post('/materials',                        [GuruContentController::class, 'storeMaterial'])->name('materials.store');
        Route::get('/materials/{id}/edit',                [GuruContentController::class, 'editMaterial'])->name('materials.edit');
        Route::put('/materials/{id}',                     [GuruContentController::class, 'updateMaterial'])->name('materials.update');
        Route::delete('/materials/{id}',                  [GuruContentController::class, 'destroyMaterial'])->name('materials.destroy');

        Route::get('/topics/{topicId}/quizzes/create', [GuruContentController::class, 'createQuiz'])->name('quizzes.create');
        Route::post('/quizzes',                        [GuruContentController::class, 'storeQuiz'])->name('quizzes.store');
        Route::get('/quizzes/{id}',                    [GuruContentController::class, 'showQuiz'])->name('quizzes.show');
        Route::get('/quizzes/{id}/edit',               [GuruContentController::class, 'editQuiz'])->name('quizzes.edit');
        Route::put('/quizzes/{id}',                    [GuruContentController::class, 'updateQuiz'])->name('quizzes.update');
        Route::delete('/quizzes/{id}',                 [GuruContentController::class, 'destroyQuiz'])->name('quizzes.destroy');

        Route::get('/quizzes/{quizId}/questions/create', [GuruContentController::class, 'createQuestion'])->name('questions.create');
        Route::post('/quizzes/{quizId}/questions',       [GuruContentController::class, 'storeQuestion'])->name('questions.store');
        Route::get('/questions/{id}/edit',                [GuruContentController::class, 'editQuestion'])->name('questions.edit');
        Route::put('/questions/{id}',                     [GuruContentController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('/questions/{id}',                  [GuruContentController::class, 'destroyQuestion'])->name('questions.destroy');
    });
});