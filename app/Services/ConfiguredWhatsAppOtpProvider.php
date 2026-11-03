<?php

declare(strict_types=1);

namespace App\Services;

final class ConfiguredWhatsAppOtpProvider implements WhatsAppOtpProviderInterface
{
    public function sendOtp(string $mobile, string $otp): bool
    {
        $apiUrl = trim((string) env('whatsapp.apiUrl'));
        $token = trim((string) env('whatsapp.token'));
        $sender = trim((string) env('whatsapp.sender'));
        $timeout = max(1, (int) env('whatsapp.timeout', 10));

        if ($apiUrl === '' || $token === '' || $sender === '' || ! function_exists('curl_init')) {
            log_message('error', 'WhatsApp OTP delivery is unavailable because provider configuration is incomplete.');
            return false;
        }

        $curl = curl_init($apiUrl);
        if ($curl === false) {
            return false;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'from' => $sender,
                'to' => $mobile,
                'type' => 'text',
                'text' => ['body' => 'Your MCC Cricket Club verification code is ' . $otp],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $response !== false && $status >= 200 && $status < 300;
    }
}
