<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use App\Models\Setting;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Log;

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
        // Load mail configuration from database
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                app(MailConfigService::class)->loadFromDatabase();
            }
        } catch (\Exception $e) {
            Log::warning('Could not load mail config from database: ' . $e->getMessage());
        }

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

    /**
     * Load mail configuration from database settings
     */
    public function loadMailConfigFromDatabase(): void
    {
        $mailConfig = [
            'default' => Setting::getValue('mail_mailer', env('MAIL_MAILER', 'log')),
        ];

        // Update SMTP mailer configuration
        Config::set('mail.default', $mailConfig['default']);

        // Update SMTP settings
        if (Setting::getValue('mail_host')) {
            Config::set('mail.mailers.smtp.host', Setting::getValue('mail_host'));
        }
        if (Setting::getValue('mail_port')) {
            Config::set('mail.mailers.smtp.port', Setting::getValue('mail_port'));
        }
        if (Setting::getValue('mail_username')) {
            Config::set('mail.mailers.smtp.username', Setting::getValue('mail_username'));
        }
        if (Setting::getValue('mail_password')) {
            Config::set('mail.mailers.smtp.password', Setting::getValue('mail_password'));
        }
        if (Setting::getValue('mail_encryption')) {
            Config::set('mail.mailers.smtp.encryption', Setting::getValue('mail_encryption'));
        }

        // Update "from" address
        if (Setting::getValue('mail_from_address')) {
            Config::set('mail.from.address', Setting::getValue('mail_from_address'));
        }
        if (Setting::getValue('mail_from_name')) {
            Config::set('mail.from.name', Setting::getValue('mail_from_name'));
        }
    }
}
