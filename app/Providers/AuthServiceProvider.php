<?php

namespace App\Providers;

use App\Models\Application;
use App\Policies\ApplicationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Application::class => ApplicationPolicy::class,
    ];
}
