<?php

use App\Providers\AppServiceProvider;
use App\Providers\RouteServiceProvider;
use Barryvdh\DomPDF\ServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

return [
    AppServiceProvider::class,
    RouteServiceProvider::class,
    PermissionServiceProvider::class,
    ServiceProvider::class,
];
