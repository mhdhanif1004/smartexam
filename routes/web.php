<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\CardSettingsController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExamPeriodController;
use App\Http\Controllers\Admin\ExamScheduleController;
use App\Http\Controllers\Admin\ExamSettingsController;
use App\Http\Controllers\Admin\LoginCardController;
use App\Http\Controllers\Admin\PlainPasswordController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuestionImportExportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportExportController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SupervisorController;
use App\Http\Controllers\Admin\SupervisorImportExportController;
use App\Http\Controllers\Admin\ViolationController;
use App\Http\Controllers\Pengawas\AttendanceController as PengawasAttendanceController;
use App\Http\Controllers\Pengawas\DashboardController as PengawasDashboardController;
use App\Http\Controllers\Pengawas\TokenController as PengawasTokenController;
use App\Http\Controllers\Pengawas\ViolationController as PengawasViolationController;
use App\Http\Controllers\Peserta\DashboardController as PesertaDashboardController;
use App\Http\Controllers\Peserta\ExamController as PesertaExamController;
use App\Http\Controllers\Peserta\ViolationController as PesertaViolationController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RedirectLocalhost;
use Illuminate\Support\Facades\Route;

Route::middleware(RedirectLocalhost::class)->group(function () {
    Route::get('/', function () {
        return auth()->check()
            ? redirect()->route(auth()->user()->dashboardRoute())
            : redirect()->route('login');
    });

    Route::get('/csrf-token', function () {
        return response()->json(['csrf_token' => csrf_token()]);
    })->name('csrf-token');

    Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin'])->name('admin.')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::controller(StudentImportExportController::class)->prefix('students')->name('students.')->group(function () {
            Route::get('/export', 'export')->name('export');
            Route::get('/import-template', 'importTemplate')->name('import-template');
            Route::post('/import-validate', 'importValidate')->name('import-validate');
            Route::post('/import-confirm', 'importConfirm')->name('import-confirm');
            Route::get('/import-failed/{file}', 'importFailed')->name('import-failed');
        });

        Route::resource('students', StudentController::class)->except(['show']);
        Route::post('students/bulk-delete', [StudentController::class, 'bulkDelete'])->name('students.bulk-delete');

        Route::controller(SupervisorImportExportController::class)->prefix('supervisors')->name('supervisors.')->group(function () {
            Route::get('/export', 'export')->name('export');
            Route::get('/import-template', 'importTemplate')->name('import-template');
            Route::post('/import-validate', 'importValidate')->name('import-validate');
            Route::post('/import-confirm', 'importConfirm')->name('import-confirm');
            Route::get('/import-failed/{file}', 'importFailed')->name('import-failed');
        });

        Route::resource('supervisors', SupervisorController::class)->except(['show']);
        Route::get('supervisors/{supervisor}', [SupervisorController::class, 'show'])->name('supervisors.show');
        Route::post('supervisors/bulk-delete', [SupervisorController::class, 'bulkDelete'])->name('supervisors.bulk-delete');
        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::get('subjects/{subject}/delete-preview', [SubjectController::class, 'deletePreview'])->name('subjects.delete-preview');
        Route::patch('subjects/{subject}/name', [SubjectController::class, 'updateName'])->name('subjects.update-name');
        Route::post('subjects/bulk-delete-preview', [SubjectController::class, 'bulkDeletePreview'])->name('subjects.bulk-delete-preview');
        Route::post('subjects/bulk-delete', [SubjectController::class, 'bulkDelete'])->name('subjects.bulk-delete');
        Route::resource('rooms', RoomController::class)->except(['show']);
        Route::post('rooms/bulk-delete', [RoomController::class, 'bulkDelete'])->name('rooms.bulk-delete');
        Route::get('rooms/{room}/detail', [RoomController::class, 'detail'])->name('rooms.detail');
        Route::resource('classrooms', ClassroomController::class)->except(['show']);
        Route::get('exam-schedules/by-date', [ExamScheduleController::class, 'byDate'])->name('exam-schedules.by-date');
        Route::get('exam-schedules/{examSchedule}/detail', [ExamScheduleController::class, 'detail'])->name('exam-schedules.detail');
        Route::resource('exam-schedules', ExamScheduleController::class)->except(['show']);
        Route::post('exam-schedules/bulk-delete', [ExamScheduleController::class, 'bulkDelete'])->name('exam-schedules.bulk-delete');

        Route::resource('exam-periods', ExamPeriodController::class)->except(['edit', 'update']);
        Route::get('exam-periods/auto-generate/create', [ExamPeriodController::class, 'autoGenerateCreate'])->name('exam-periods.auto-generate.create');
        Route::post('exam-periods/auto-generate', [ExamPeriodController::class, 'autoGenerateStore'])->name('exam-periods.auto-generate.store');
        Route::get('exam-periods/{examPeriod}/groups/create', [ExamPeriodController::class, 'groupsCreate'])->name('exam-periods.groups.create');
        Route::post('exam-periods/{examPeriod}/groups', [ExamPeriodController::class, 'groupsStore'])->name('exam-periods.groups.store');
        Route::post('exam-periods/{examPeriod}/supervisor-rotation', [ExamPeriodController::class, 'supervisorRotation'])->name('exam-periods.supervisor-rotation');
        Route::get('exam-periods/{examPeriod}/rooms/{room}/roster', [ExamPeriodController::class, 'roomRoster'])->name('exam-periods.room-roster');
        Route::put('exam-periods/{examPeriod}/room-overrides', [ExamPeriodController::class, 'updateRoomOverrides'])->name('exam-periods.room-overrides.update');

        Route::get('questions/by-subject/{subject}', [QuestionController::class, 'bySubject'])->name('questions.by-subject');
        Route::post('questions/bulk-delete', [QuestionController::class, 'bulkDelete'])->name('questions.bulk-delete');
        Route::post('questions/bulk-edit', [QuestionController::class, 'bulkEdit'])->name('questions.bulk-edit');
        Route::patch('questions/bulk-classrooms', [QuestionController::class, 'bulkUpdateClassrooms'])->name('questions.bulk-classrooms');
        Route::post('questions/group-delete-preview', [QuestionController::class, 'groupDeletePreview'])->name('questions.group-delete-preview');
        Route::post('questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
        Route::patch('questions/{question}/toggle-active', [QuestionController::class, 'toggleActive'])->name('questions.toggle-active');
        Route::resource('questions', QuestionController::class)->except(['show']);

        Route::controller(QuestionImportExportController::class)->prefix('questions')->name('questions.')->group(function () {
            Route::get('/export', 'export')->name('export');
            Route::get('/import-template/{type}', 'importTemplate')->name('import-template');
            Route::post('/import-validate', 'importValidate')->name('import-validate');
            Route::post('/import-confirm', 'importConfirm')->name('import-confirm');
            Route::get('/import-failed/{file}', 'importFailed')->name('import-failed');
        });

        Route::get('/users/{user}/plain-password', [PlainPasswordController::class, 'show'])->name('users.plain-password');

        Route::controller(LoginCardController::class)->prefix('student-cards')->name('student-cards.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/preview', 'preview')->name('preview');
            Route::post('/print', 'print')->name('print');
        });

        Route::controller(CardSettingsController::class)->prefix('card-settings')->name('card-settings.')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
        });

        Route::controller(ExamSettingsController::class)->prefix('exam-settings')->name('exam-settings.')->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
        });

        Route::resource('attendance', AdminAttendanceController::class)->only(['index'])->names('attendance');
        Route::get('attendance/summary', [AdminAttendanceController::class, 'summary'])->name('attendance.summary');

        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
            Route::get('/results', 'index')->name('index');
            Route::get('/results/export-excel', 'exportExcel')->name('export-excel');
            Route::get('/results/export-pdf', 'exportPdf')->name('export-pdf');
        });

        Route::get('/violations', [ViolationController::class, 'index'])->name('violations.index');
        Route::patch('/violations/{examSession}/lock', [ViolationController::class, 'toggleLock'])->name('violations.lock');
    });

    Route::prefix('pengawas')->middleware(['auth', 'verified', 'role:pengawas'])->name('pengawas.')->group(function () {
        Route::get('/dashboard', PengawasDashboardController::class)->name('dashboard');

        Route::controller(PengawasViolationController::class)->prefix('violations')->name('violations.')->group(function () {
            Route::get('/latest', 'recent')->name('latest');
            Route::get('/recent', 'recent')->name('recent');
            Route::patch('/{violation}/handle', 'handle')->name('handle');
        });

        Route::controller(PengawasAttendanceController::class)->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/{schedule}/confirm', 'confirm')->name('confirm');
            Route::post('/', 'update')->name('update');
        });

        Route::controller(PengawasTokenController::class)->prefix('tokens')->name('tokens.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/generate', 'generate')->name('generate');
        });
    });

    Route::prefix('peserta')->middleware(['auth', 'verified', 'role:peserta'])->name('peserta.')->group(function () {
        Route::get('/dashboard', PesertaDashboardController::class)->name('dashboard');

        Route::controller(PesertaExamController::class)->prefix('exams')->name('exams.')->group(function () {
            Route::get('/{schedule}/token', 'token')->name('token');
            Route::post('/{schedule}/token', 'validateToken')->name('token.validate');
            Route::get('/{schedule}/work', 'work')->name('work');
            Route::get('/{schedule}/status', 'status')->name('status');
            Route::post('/{schedule}/save-answer', 'saveAnswer')->name('save-answer');
            Route::post('/{schedule}/questions/{question}/toggle-doubtful', 'toggleDoubtful')->name('questions.toggle-doubtful');
            Route::post('/{schedule}/submit', 'submit')->name('submit');
            Route::get('/{schedule}/finished', 'finished')->name('finished');
        });

        Route::controller(PesertaViolationController::class)->prefix('exams')->name('exams.')->group(function () {
            Route::post('/{schedule}/violation', 'store')->name('violation');
        });
    });

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
