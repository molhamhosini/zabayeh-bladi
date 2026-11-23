<?php

use Illuminate\Support\Facades\Route;

// The domain serves the API and the dashboards; the site root opens the admin login.
Route::redirect('/', '/admin');
