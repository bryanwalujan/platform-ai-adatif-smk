<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge jumlah guru pending di sidebar panel admin (semua view di bawah admin.layout)
        View::composer('admin.layout', function ($view) {
            $view->with('guruPendingBadge', User::where('role', 'guru')->where('status', 'pending')->count());
        });
    }
}
