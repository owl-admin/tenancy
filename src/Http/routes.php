<?php

use Illuminate\Support\Facades\Route;
use OwlAdmin\Tenancy\Http\Controllers\TenantController;
use OwlAdmin\Tenancy\Http\Controllers\TenantSwitchController;
use OwlAdmin\Tenancy\Http\Controllers\TenantMemberController;

Route::resource('owl-tenancy/tenants', TenantController::class);
Route::resource('owl-tenancy/members', TenantMemberController::class);

Route::get('owl-tenancy/switch', [TenantSwitchController::class, 'index']);
Route::post('owl-tenancy/switch', [TenantSwitchController::class, 'switch']);
Route::get('owl-tenancy/current', [TenantSwitchController::class, 'current']);
Route::get('owl-tenancy/options', [TenantSwitchController::class, 'options']);
