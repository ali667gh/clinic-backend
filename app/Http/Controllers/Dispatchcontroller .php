<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Patient;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * مدیریت ارسالی‌ها
 * برای وقتی که پیامک لینک جوابدهی به هر دلیلی به بیمار نرسیده است.
 * دسترسی: فقط admin و staff_level_1 (در routes/api.php)
 */
class DispatchController extends Controller
{
    private const RECENT_HOURS = 24;
    private const LIST_LIMIT   = 500;

    // همان عنوانی که notifyPatient در ArchiveController در تاریخچه ثبت می‌کند
    private const SMS_ACTION = 'ارسال پیامک';

    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    // =========================================================================
    // ۱. جوابدهی‌های ۲۴ ساعت اخیر
    // =========================================================================

    public function recent(): JsonResponse
    {
        $since = now()->subHours(self::RECENT_HOURS);

        $archives = Archive::query()
            ->select([
                'id',
                'patient_name',
                'national_code',
                'file_number',
                'mobile',
                'issued_by_name',
                'history',
                'created_at',
            ])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $archives->map(fn(Archive $a) => $this->archivePayload($a))->values(),
            'meta'   => [
                'hours' => self::RECENT_HOURS,
                'since' => $since->toIso8601String(),
                'total' => $archives->count(),
            ],
        ]);
    }

    // =========================================================================
    // ۲. بیماران ویرایش‌شده
    // =========================================================================

    public function editedPatients(): JsonResponse
    {
        $patients = Patient::query()
            ->whereNotNull('edited_at')
            ->where(function ($q) {
                $q->whereNull('link_resent_at')
                    ->orWhereColumn('link_resent_at', '<', 'edited_at');
            })
            ->orderByDesc('edited_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        $rows = [];

        foreach ($patients as $patient) {
            $archive = $this->latestArchiveFor($patient, ['id', 'created_at']);

            // بدون پرونده جوابدهی لینکی برای ارسال وجود ندارد
            if (!$archive) {
                continue;
            }

            // اگر جوابدهی بعد از ویرایش ثبت شده، پیامک با اطلاعات درست رفته است
            if ($archive->created_at && Carbon::parse($archive->created_at)->gt(Carbon::parse($patient->edited_at))) {
                continue;
            }

            $rows[] = [
                'id'                 => $patient->id,
                'full_name'          => $patient->full_name,
                'file_number'        => $patient->file_number,
                'national_code'      => $patient->national_code,
                'mobile'             => $patient->mobile,
                'edited_at'          => Carbon::parse($patient->edited_at)->toIso8601String(),
                'archive_id'         => $archive->id,
                'archive_created_at' => $archive->created_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
            'meta'   => ['total' => count($rows)],
        ]);
    }

    // =========================================================================
    // ۳. ارسال دوباره‌ی لینک
    // =========================================================================

    /**
     * ارسال دوباره‌ی لینک یک جوابدهی (از لیست ۲۴ ساعته)
     */
    public function resendArchive($id): JsonResponse
    {
        $archive = Archive::findOrFail($id);

        $patient = Patient::query()
            ->where('national_code', $archive->national_code)
            ->first();

        return $this->deliver($archive, $patient);
    }

    /**
     * ارسال دوباره‌ی لینک آخرین جوابدهی یک بیمار ویرایش‌شده
     */
    public function resendPatient($id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $archive = $this->latestArchiveFor($patient);

        if (!$archive) {
            return response()->json([
                'message' => 'برای این بیمار هنوز پرونده جوابدهی ثبت نشده است.',
            ], 422);
        }

        return $this->deliver($archive, $patient);
    }

    private function deliver(Archive $archive, ?Patient $patient): JsonResponse
    {
        $mobile = $this->resolveMobile($archive, $patient);

        if (!$mobile) {
            return response()->json([
                'message' => 'شماره موبایل معتبری برای این بیمار ثبت نشده است. ابتدا در مدیریت بیماران اصلاح کنید.',
            ], 422);
        }

        try {
            $sent = $this->smsService->sendResultLink(
                $mobile,
                (string) $archive->patient_name,
                (string) $archive->tracking_token
            );
        } catch (\Throwable $e) {
            Log::error('Dispatch resend failed: ' . $e->getMessage(), [
                'archive_id' => $archive->id,
                'mobile'     => $mobile,
            ]);
            $sent = false;
        }

        if (!$sent) {
            return response()->json([
                'message' => 'ارسال پیامک ناموفق بود. شماره موبایل و تنظیمات سرویس پیامک را بررسی کنید.',
            ], 502);
        }

        $this->appendHistory($archive, $mobile);
        $this->markPatientResent($patient);

        return response()->json([
            'status'  => 'success',
            'message' => 'لینک جوابدهی برای شماره ' . $this->maskMobile($mobile) . ' ارسال شد.',
            'data'    => [
                'archive_id' => $archive->id,
                'mobile'     => $this->maskMobile($mobile),
                'resent_at'  => now()->toIso8601String(),
            ],
        ]);
    }

    // =========================================================================
    // ابزارهای کمکی
    // =========================================================================

    private function archivePayload(Archive $archive): array
    {
        $resends = array_values(array_filter(
            $this->asArray($archive->history),
            fn($h) => is_array($h) && ($h['action'] ?? null) === self::SMS_ACTION
        ));

        $lastResend = !empty($resends) ? end($resends) : null;

        return [
            'id'             => $archive->id,
            'patient_name'   => $archive->patient_name,
            'national_code'  => $archive->national_code,
            'file_number'    => $archive->file_number,
            'mobile'         => $archive->mobile,
            'issued_by_name' => $archive->issued_by_name,
            'created_at'     => $archive->created_at?->toIso8601String(),
            'resend_count'   => count($resends),
            'last_resent_at' => $lastResend['created_at'] ?? null,
        ];
    }

    /**
     * آخرین پرونده بایگانی بیمار.
     * علاوه بر کد ملی و شماره پرونده‌ی فعلی، مقادیر قبلی (قبل از ویرایش) هم بررسی می‌شوند؛
     * مگر اینکه الان متعلق به بیمار دیگری باشند.
     */
    private function latestArchiveFor(Patient $patient, array $columns = ['*']): ?Archive
    {
        $legacy = $this->decodeLegacy($patient->getAttribute('legacy_identifiers'));

        $nationalCodes = array_filter([trim((string) $patient->national_code)], fn($v) => $v !== '');
        $fileNumbers   = array_filter([trim((string) $patient->file_number)], fn($v) => $v !== '');

        if (!empty($legacy['national_codes'])) {
            $taken = Patient::query()
                ->whereIn('national_code', $legacy['national_codes'])
                ->where('id', '!=', $patient->id)
                ->pluck('national_code')
                ->all();
            $nationalCodes = array_merge($nationalCodes, array_diff($legacy['national_codes'], $taken));
        }

        if (!empty($legacy['file_numbers'])) {
            $taken = Patient::query()
                ->whereIn('file_number', $legacy['file_numbers'])
                ->where('id', '!=', $patient->id)
                ->pluck('file_number')
                ->all();
            $fileNumbers = array_merge($fileNumbers, array_diff($legacy['file_numbers'], $taken));
        }

        $nationalCodes = array_values(array_unique($nationalCodes));
        $fileNumbers   = array_values(array_unique($fileNumbers));

        if (empty($nationalCodes) && empty($fileNumbers)) {
            return null;
        }

        return Archive::query()
            ->select($columns)
            ->where(function ($q) use ($nationalCodes, $fileNumbers) {
                if (!empty($nationalCodes)) {
                    $q->whereIn('national_code', $nationalCodes);
                }
                if (!empty($fileNumbers)) {
                    $q->orWhereIn('file_number', $fileNumbers);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * اولویت با موبایل فعلی بیمار (چون معمولاً همان اصلاح شده) و بعد موبایل ثبت‌شده در بایگانی
     */
    private function resolveMobile(Archive $archive, ?Patient $patient): ?string
    {
        $formData = $this->asArray($archive->form_data);

        $candidates = [
            $patient?->mobile,
            $archive->mobile,
            $formData['mobile'] ?? null,
            $formData['phone'] ?? null,
            $formData['patient']['mobile'] ?? null,
            $formData['patient']['phone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeMobile($candidate);
            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeMobile($raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $s = str_replace($persian, $english, (string) $raw);
        $s = str_replace($arabic, $english, $s);
        $digits = preg_replace('/\D/', '', $s) ?? '';

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }

    private function maskMobile(string $mobile): string
    {
        return substr($mobile, 0, 4) . '***' . substr($mobile, -4);
    }

    private function appendHistory(Archive $archive, string $mobile): void
    {
        try {
            $history = $this->asArray($archive->history);
            $user    = Auth::user();
            $name    = $user->name ?? $user->username ?? 'کاربر پذیرش';

            $history[] = [
                'id'                 => count($history) + 1,
                'user'               => ['name' => $name],
                'user_name'          => $name,
                'action'             => self::SMS_ACTION,
                'action_description' => "لینک پرتال از بخش مدیریت ارسالی‌ها مجدداً به شماره {$mobile} ارسال شد.",
                'created_at'         => now()->toIso8601String(),
            ];

            $archive->update(['history' => $history]);
        } catch (\Throwable $e) {
            Log::warning('Dispatch history append failed: ' . $e->getMessage(), ['archive_id' => $archive->id]);
        }
    }

    private function markPatientResent(?Patient $patient): void
    {
        if (!$patient) {
            return;
        }

        try {
            Patient::query()->whereKey($patient->getKey())->update(['link_resent_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Dispatch markPatientResent failed: ' . $e->getMessage(), ['patient_id' => $patient->getKey()]);
        }
    }

    private function asArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?: [];
    }

    private function decodeLegacy($raw): array
    {
        $data = $this->asArray($raw);

        $clean = function ($list) {
            return array_values(array_unique(array_filter(
                array_map('strval', (array) $list),
                fn($v) => $v !== ''
            )));
        };

        return [
            'national_codes' => $clean($data['national_codes'] ?? []),
            'file_numbers'   => $clean($data['file_numbers'] ?? []),
        ];
    }
}
