<?php

use App\Enums\TripDocument;
use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TripDocumentController;
use App\Livewire\Requests\CreateRequest;
use App\Livewire\Requests\RequestList;
use App\Livewire\Requests\ShowRequest;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('requests', RequestList::class)->name('requests.index');
    Route::get('requests/create', CreateRequest::class)->name('requests.create');
    Route::get('requests/{trip}', ShowRequest::class)->whereNumber('trip')->name('requests.show');
    Route::get('requests/{trip}/attachments/{attachment}', AttachmentDownloadController::class)
        ->middleware('signed')->whereNumber(['trip', 'attachment'])->name('attachments.download');
    Route::get('requests/{trip}/documents/{type}', TripDocumentController::class)
        ->whereNumber('trip')->whereIn('type', array_column(TripDocument::cases(), 'value'))->name('requests.document');

    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/settings.php';
