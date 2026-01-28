<?php

use App\Http\Controllers\CardAudioController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\JobPageImageController;
use App\Livewire\CardReview;
use App\Livewire\Dashboard;
use App\Livewire\ExportHistory;
use App\Livewire\JobImports;
use App\Livewire\JobProgress;
use App\Livewire\LearnSession;
use App\Livewire\LibraryDashboard;
use App\Livewire\StudySession;
use App\Livewire\UploadJob;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/library', LibraryDashboard::class)->name('library');
    Route::get('/jobs', JobImports::class)->name('jobs.index');
    Route::get('/jobs/create', UploadJob::class)->name('jobs.create');
    Route::get('/jobs/{job}', JobProgress::class)->name('jobs.progress');
    Route::get('/jobs/{job}/review', CardReview::class)->name('jobs.review');
    Route::get('/decks/{deck}/study', StudySession::class)->name('decks.study');
    Route::get('/decks/{deck}/learn', LearnSession::class)->name('decks.learn');
    Route::get('/exports', ExportHistory::class)->name('exports.index');
    Route::get('/jobs/{job}/pages/{page}/image', JobPageImageController::class)
        ->middleware('signed')
        ->name('job-pages.image');
    Route::get('/cards/{card}/audio', CardAudioController::class)
        ->middleware('signed')
        ->name('cards.audio');
    Route::get('/exports/{export}/download', ExportDownloadController::class)
        ->middleware('signed')
        ->name('exports.download');
});

require __DIR__.'/settings.php';
