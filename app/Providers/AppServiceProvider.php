<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

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
        // Share email logo URL from settings with all email templates (setting key: logo)
        View::composer('emails.*', function ($view) {
            $logoUrl = config('app.url') . '/images/logo.png';
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    $fromSettings = Setting::getValue('logo');
                    if (! empty($fromSettings)) {
                        $logoUrl = $fromSettings;
                    }
                }
            } catch (\Exception $e) {
                // keep default
            }
            $view->with('email_logo_url', $logoUrl);
        });
    }
}
