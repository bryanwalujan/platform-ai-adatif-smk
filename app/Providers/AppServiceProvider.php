<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SchoolContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SchoolContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Scope all foreign-key existence validation as well as Eloquent queries.
        Validator::extend('school_exists', function ($attribute, $value, $parameters) {
            [$table, $column] = [$parameters[0], $parameters[1] ?? 'id'];
            $id = app(SchoolContext::class)->id;

            return $id !== null && DB::table($table)
                ->where('school_id', $id)->where($column, $value)->exists();
        }, 'Data :attribute tidak ditemukan di sekolah Anda.');

        // Badge jumlah guru pending di sidebar panel admin (semua view di bawah admin.layout)
        View::composer('admin.layout', function ($view) {
            $view->with('guruPendingBadge', User::where('role', 'guru')->where('status', 'pending')->count());
        });
    }
}
