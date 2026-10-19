<?php

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
});

require __DIR__.'/settings.php';
