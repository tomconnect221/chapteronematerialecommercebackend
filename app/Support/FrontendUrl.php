<?php

namespace App\Support;

use RuntimeException;

final class FrontendUrl
{
    public static function base(): string
    {
        $isProduction = config('app.env') === 'production';
        $frontendUrl = config('app.frontend_url');

        if (is_string($frontendUrl) && trim($frontendUrl) !== '') {
            $frontendUrl = trim($frontendUrl);
            $parsedFrontendUrl = parse_url($frontendUrl);

            if (
                !is_array($parsedFrontendUrl) ||
                empty($parsedFrontendUrl['scheme']) ||
                empty($parsedFrontendUrl['host'])
            ) {
                throw new RuntimeException('FRONTEND_URL must be an absolute URL.');
            }

            $host = strtolower(trim($parsedFrontendUrl['host'], '[]'));
            if (
                $isProduction &&
                (
                    $parsedFrontendUrl['scheme'] !== 'https' ||
                    $host === 'localhost' ||
                    str_ends_with($host, '.localhost') ||
                    str_starts_with($host, '127.') ||
                    $host === '::1'
                )
            ) {
                throw new RuntimeException('FRONTEND_URL must use the public HTTPS frontend host in production.');
            }

            return rtrim($frontendUrl, '/');
        }

        if ($isProduction) {
            throw new RuntimeException('FRONTEND_URL must be configured to the public frontend host in production.');
        }

        $appUrl = parse_url((string) config('app.url'));
        if (!is_array($appUrl) || empty($appUrl['scheme']) || empty($appUrl['host'])) {
            throw new RuntimeException('Configure APP_URL or FRONTEND_URL with an absolute URL.');
        }

        $port = isset($appUrl['port']) ? ':' . $appUrl['port'] : '';

        return $appUrl['scheme'] . '://' . $appUrl['host'] . $port;
    }
}
