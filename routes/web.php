<?php

use App\Http\Controllers\PdfController;
use App\Livewire\ScanPage;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

// Named 'login' route so Laravel's auth middleware can redirect guests (e.g. /scan)
Route::get('/login', fn () => redirect()->route('filament.staff.auth.login'))->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/scan', ScanPage::class)->name('scan');

    Route::get('/orders/{order}/job-ticket', [PdfController::class, 'jobTicket'])->name('orders.job-ticket');
    Route::get('/stock-items/{stockItem}/label', [PdfController::class, 'stockLabel'])->name('stock-items.label');
});
