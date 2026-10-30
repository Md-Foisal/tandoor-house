<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    FortifyServiceProvider::class,
    NotificationServiceProvider::class,
];
