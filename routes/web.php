<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});
Route::middleware(['auth', ActiveUser::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', [ReportController::class, 'dashboard'])->name('dashboard');
    Route::resource('shifts', ShiftController::class)->except('show');
    Route::post('/shifts/{shift}/transition', [ShiftController::class, 'transition'])->name('shifts.transition');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/daily', [ReportController::class, 'daily'])->name('daily');
    Route::get('/notifications', [ReportController::class, 'notifications'])->name('notifications');
    Route::get('/activity', [ReportController::class, 'activity'])->name('activity');
    Route::get('/employees', [ManagementController::class, 'employees'])->name('employees');
    Route::post('/employees', [ManagementController::class, 'saveEmployee'])->name('employees.store');
    Route::put('/employees/{employee}', [ManagementController::class, 'saveEmployee'])->name('employees.update');
    Route::get('/employees/{employee}', [ManagementController::class, 'employee'])->name('employees.show');
    Route::get('/settings', [ManagementController::class, 'settings'])->name('settings');
    Route::post('/settings', [ManagementController::class, 'saveSettings'])->name('settings.save');
    Route::post('/types', [ManagementController::class, 'saveType'])->name('types.store');
    Route::put('/types/{type}', [ManagementController::class, 'saveType'])->name('types.update');
    Route::get('/users', [ManagementController::class, 'users'])->name('users');
    Route::post('/users', [ManagementController::class, 'saveUser'])->name('users.store');
    Route::put('/users/{user}', [ManagementController::class, 'saveUser'])->name('users.update');
    Route::post('/backup', [ManagementController::class, 'backup'])->middleware('throttle:3,1')->name('backup');
    Route::get('/imports', [ImportController::class, 'index'])->name('imports');
    Route::post('/imports', [ImportController::class, 'preview'])->middleware('throttle:10,1')->name('imports.preview');
    Route::get('/imports/{batch}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{batch}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
});
