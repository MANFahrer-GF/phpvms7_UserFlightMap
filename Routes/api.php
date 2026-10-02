<?php

use Illuminate\Support\Facades\Route;
use Modules\UserFlightMap\Http\Controllers\Api\ProfileMapController;

// Prefixed with /userflightmap (see RouteServiceProvider)
Route::get("/profile/{id}", [ProfileMapController::class, "profile"])->whereNumber("id")->name("profile");
