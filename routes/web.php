<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Auth/Login'))->name('login');

Route::get('/kasir/login', fn () => Inertia::render('Staff/Login'))->name('staff.login');

Route::middleware('auth:staff')->get('/kasir', fn () => Inertia::render('Cashier/Dashboard'))->name('cashier');

Route::middleware(['auth:staff', 'role:admin'])->get('/admin', fn () => Inertia::render('Admin/Dashboard'))->name('admin');
