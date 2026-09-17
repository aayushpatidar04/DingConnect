<?php

use App\Providers\AppServiceProvider;
use App\Providers\RouteServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Barryvdh\DomPDF\ServiceProvider;

return [
    AppServiceProvider::class,
    RouteServiceProvider::class,
    PermissionServiceProvider::class,
    ServiceProvider::class,
];
