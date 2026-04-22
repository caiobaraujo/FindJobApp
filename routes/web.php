<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\JobLeadController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [ApplicationController::class, 'dashboard'])->name('dashboard');
    Route::post('/job-leads/import', [JobLeadController::class, 'importFromUrl'])->name('job-leads.import');
    Route::resource('job-leads', JobLeadController::class)->except(['show']);
    Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus'])
        ->name('applications.status.update');
    Route::resource('applications', ApplicationController::class)->except(['show']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
