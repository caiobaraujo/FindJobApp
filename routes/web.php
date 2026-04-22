<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\JobLeadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
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
    Route::get('/dashboard', [JobLeadController::class, 'dashboard'])->name('dashboard');
    Route::get('/matched-jobs', [JobLeadController::class, 'index'])->name('matched-jobs.index');
    Route::post('/locale', function (Request $request) {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:pt,en,es'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back();
    })->name('locale.switch');
    Route::get('/job-leads/import', function () {
        return redirect()->route('job-leads.index', ['focus' => 'import']);
    })->name('job-leads.import.entry');
    Route::post('/job-leads/import', [JobLeadController::class, 'importFromUrl'])->name('job-leads.import');
    Route::resource('job-leads', JobLeadController::class)->except(['show']);
    Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus'])
        ->name('applications.status.update');
    Route::resource('applications', ApplicationController::class)->except(['show']);
    Route::get('/resume-profile', [UserProfileController::class, 'show'])->name('resume-profile.show');
    Route::get('/resume-profile/create', [UserProfileController::class, 'create'])->name('resume-profile.create');
    Route::post('/resume-profile', [UserProfileController::class, 'store'])->name('resume-profile.store');
    Route::patch('/resume-profile', [UserProfileController::class, 'update'])->name('resume-profile.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
