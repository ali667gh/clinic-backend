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
        // استانداردسازی شماره تماس به 09xxxxxxxxx
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        if (str_starts_with($mobile, '98')) {
            $mobile = '0' . substr($mobile, 2);
        }

        // متن کامل برای ارسال عادی (Bulk)
        $link = "https://report-tehranep.site/portal/{$token}";
        $message = "بیمار گرامی {$patientName}، نتیجه آزمایش و مدارک شما آماده است.\nمشاهده در پرتال: {$link}";

        if (empty($this->apiKey)) {
            Log::warning("SmsService: API Key تعریف نشده است. متن لاگ شد: [{$mobile}] {$message}");
            return true;
        }

        try {
            // ارسال بر اساس پترن سریع (Verify)
            if (!empty($this->templateId)) {
                return $this->sendViaTemplate($mobile, [
                    'PATIENTNAME' => $patientName,
                    'TOKEN'       => $token,
                ]);
            }

            // ارسال پیام متنی معمولی (در صورت عدم تنظیم Template)
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
     * ارسال بر اساس قالب sms.ir
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
     * ارسال متنی عادی (Bulk)
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
