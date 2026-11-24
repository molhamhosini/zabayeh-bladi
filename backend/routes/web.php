<?php

use App\Http\Controllers\Admin\InvoiceController;
use Illuminate\Support\Facades\Route;

// The domain serves the API and the dashboards; the site root opens the admin login.
Route::redirect('/', '/admin');

// Printable tax invoice, opened from the order page in the admin or branch portal.
Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show'])
    ->middleware('auth')
    ->name('orders.invoice');
