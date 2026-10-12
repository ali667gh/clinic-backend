<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected ?string $apiKey;
    protected ?string $lineNumber;
    protected ?string $templateId;

    public function __construct()
    {
        $this->apiKey = config('services.smsir.api_key', env('SMSIR_API_KEY'));
        $this->lineNumber = config('services.smsir.line_number', env('SMSIR_LINE_NUMBER', '30007732'));
        $this->templateId = config('services.smsir.template_id', env('SMSIR_TEMPLATE_ID'));
    }

    /**
     * ارسال لینک نتیجه به شماره موبایل بیمار
     */
    public function sendResultLink(string $mobile, string $patientName, string $token): bool
    {
        // نرمال‌سازی شماره تماس به فرمت استاندارد ایران (مثلاً 09123456789)
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        if (str_starts_with($mobile, '98')) {
            $mobile = '0' . substr($mobile, 2);
        }

        // ساخت لینک پرتال بیمار
        $baseUrl = config('app.frontend_url', config('app.url', url('/')));
        $link = rtrim($baseUrl, '/') . "/portal/{$token}";

        $message = "بیمار گرامی {$patientName}، نتیجه آزمایش و مدارک شما آماده است.\nمشاهده در پرتال: {$link}";

        // بررسی وجود کلید API
        if (empty($this->apiKey)) {
            Log::warning("SmsService: API Key تعریف نشده است. پیامک در لاگ ثبت شد: [{$mobile}] {$message}");
            return true;
        }

        try {
            // اگر قالب پترن (Verify) تنظیم شده باشد
            if (!empty($this->templateId)) {
                return $this->sendViaTemplate($mobile, [
                    'name' => $patientName,
                    'link' => $link,
                ]);
            }

            // ارسال مستقیم متنی عادی
            return $this->sendViaBulk($mobile, $message);
        } catch (\Throwable $e) {
            Log::error("SmsService Exception: " . $e->getMessage(), [
                'mobile' => $mobile,
                'patient' => $patientName,
            ]);
            return false;
        }
    }

    /**
     * ارسال بر اساس پترن/قالب sms.ir
     */
    protected function sendViaTemplate(string $mobile, array $parameters): bool
    {
        $paramsFormatted = [];
        foreach ($parameters as $name => $value) {
            $paramsFormatted[] = [
                'name' => (string) $name,
                'value' => (string) $value,
            ];
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(10)->post('https://api.sms.ir/v1/send/verify', [
            'mobile' => $mobile,
            'templateId' => (int) $this->templateId,
            'parameters' => $paramsFormatted,
        ]);

        $resData = $response->json();
        if ($response->successful() && isset($resData['status']) && ($resData['status'] == 1 || $resData['status'] === true)) {
            Log::info("SMS Template sent successfully to {$mobile}");
            return true;
        }

        Log::error("SMS Template failed: " . $response->body());
        return false;
    }

    /**
     * ارسال مستقیم متنی (Bulk)
     */
    protected function sendViaBulk(string $mobile, string $message): bool
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(10)->post('https://api.sms.ir/v1/send/bulk', [
            'lineNumber' => (int) $this->lineNumber,
            'messageText' => $message,
            'mobiles' => [$mobile],
        ]);

        $resData = $response->json();
        if ($response->successful() && isset($resData['status']) && ($resData['status'] == 1 || $resData['status'] === true)) {
            Log::info("SMS Bulk sent successfully to {$mobile}");
            return true;
        }

        Log::error("SMS Bulk failed: " . $response->body());
        return false;
    }
}
