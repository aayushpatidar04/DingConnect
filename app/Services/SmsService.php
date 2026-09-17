<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $authKey;
    protected string $senderId;
    protected string $templateId;

    public function __construct()
    {
        $this->authKey = config('platform.sms.msg91.auth_key');
        $this->senderId = config('platform.sms.msg91.sender_id', 'MKNETW');
        $this->templateId = config('platform.sms.msg91.template_id');
    }

    /**
     * Send OTP for phone verification
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        try {
            $response = Http::asForm()->post('https://control.msg91.com/api/v5/flow/', [
                'template_id' => $this->templateId,
                'short_url' => '0',
                'data' => [
                    'var1' => $otp,
                ],
                'recipients' => [
                    [
                        'mobiles' => $this->formatPhone($phone),
                    ],
                ],
            ], [
                'authkey' => $this->authKey,
                'Content-Type' => 'application/json',
            ]);

            Log::info("OTP sent to {$phone}: " . $response->body());
            return $response->successful();

        } catch (\Exception $e) {
            Log::error("SMS OTP Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send generic SMS
     */
    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::asForm()->post('https://api.msg91.com/api/v5/flow/', [
                'template_id' => $this->templateId,
                'short_url' => '0',
                'data' => [
                    'var1' => $message,
                ],
                'recipients' => [
                    [
                        'mobiles' => $this->formatPhone($phone),
                    ],
                ],
            ], [
                'authkey' => $this->authKey,
                'Content-Type' => 'application/json',
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("SMS Send Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send recharge success notification
     */
    public function sendRechargeSuccess(string $phone, string $mobileNumber, float $amount, string $operator): bool
    {
        $message = "Your recharge of £ {$amount} for {$mobileNumber} on {$operator} was successful. Thank you!";
        return $this->send($phone, $message);
    }

    /**
     * Send recharge failure notification
     */
    public function sendRechargeFailed(string $phone, string $mobileNumber, float $amount): bool
    {
        $message = "Your recharge of £ {$amount} for {$mobileNumber} failed. Amount has been refunded to your wallet.";
        return $this->send($phone, $message);
    }

    /**
     * Format phone number to E.164 format
     */
    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (!str_starts_with($phone, '91') && strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        return $phone;
    }
}
