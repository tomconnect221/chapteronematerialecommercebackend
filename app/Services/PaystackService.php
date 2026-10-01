<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class PaystackService
{
    protected $baseUrl;
    protected $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.paystack.co';
        $this->getSecretKey();
    }

    protected function getSecretKey(): void
    {
        // Attempt to fetch secret key from cache, fall back to database
        $settings = Cache::rememberForever('app_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        $this->secretKey = $settings['paystack_secret_key'] ?? '';
    }

    /**
     * Get currency from settings (cached)
     */
    protected function getCurrency(): string
    {
        // Retrieve cached settings
        $settings = Cache::rememberForever('app_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        // Default to NGN if not set
        return $settings['currency'] ?? 'NGN';
    }

    /**
     * Initialize a Paystack payment
     */
    public function initializePayment($email, $amount, $metadata = [])
    {
        $currency = $this->getCurrency();

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => (int) round((float) $amount * 100), // Paystack uses kobo (smallest unit)
                'metadata' => $metadata,
                'currency' => $currency,
            ]);

        return $response->json();
    }

    /**
     * Verify a Paystack payment
     */
    public function verifyPayment($reference)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        return $response->json();
    }
}
