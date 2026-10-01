<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;

class MailConfigService
{
    public function loadFromDatabase(): void
    {
        Config::set('mail.default', Setting::getValue(
            'mail_mailer',
            env('MAIL_MAILER', 'log')
        ));

        if ($host = Setting::getValue('mail_host')) {
            Config::set('mail.mailers.smtp.host', $host);
        }

        if ($port = Setting::getValue('mail_port')) {
            Config::set('mail.mailers.smtp.port', $port);
        }

        if ($username = Setting::getValue('mail_username')) {
            Config::set('mail.mailers.smtp.username', $username);
        }

        if ($password = Setting::getValue('mail_password')) {
            Config::set('mail.mailers.smtp.password', $password);
        }

        if ($encryption = Setting::getValue('mail_encryption')) {
            Config::set('mail.mailers.smtp.encryption', $encryption);
        }

        if ($from = Setting::getValue('mail_from_address')) {
            Config::set('mail.from.address', $from);
        }

        if ($name = Setting::getValue('mail_from_name')) {
            Config::set('mail.from.name', $name);
        }
    }
}
