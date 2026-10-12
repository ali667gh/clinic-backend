<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Archive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * استخراج هوشمند و مطمئن نام کاربر از هر ساختاری
     */
    private function resolveUserName($log, $archive, $formData): string
    {
        if (is_array($log)) {
            // ۱. بررسی ساختارهای داخل آبجکت کاربر
            if (isset($log['user']) && is_array($log['user'])) {
                $u = $log['user'];
                $found = $u['name'] ?? $u['full_name'] ?? $u['username'] ?? null;
                if (!empty($found) && $found !== 'کاربر سیستم') {
                    return (string) $found;
                }
            }

            // ۲. بررسی کلیدهای مستقیم سطح لاگ
            $directKeys = ['user_name', 'userName', 'operator_name', 'operator', 'creator_name', 'registered_by', 'name'];
            foreach ($directKeys as $key) {
                if (!empty($log[$key]) && is_string($log[$key]) && $log[$key] !== 'کاربر سیستم') {
                    return $log[$key];
                }
            }
        }

        // ۳. در صورت نبود، ارجاع به صادرکننده کل رکورد آرشیو یا کاربر فرم
        if (!empty($archive->issued_by_name) && $archive->issued_by_name !== 'کاربر سیستم') {
            return (string) $archive->issued_by_name;
        }

        if (!empty($formData['created_by_name'])) {
            return (string) $formData['created_by_name'];
        }

        if (!empty($formData['issued_by_name'])) {
            return (string) $formData['issued_by_name'];
        }

        return 'کاربر سیستم';
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Archive::query();

        // فیلتر جستجو
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('national_code', 'like', "%{$search}%")
                    ->orWhere('file_number', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('issued_by_name', 'like', "%{$search}%")
                    ->orWhere('form_data', 'like', "%{$search}%");
            });
        }

        // فیلتر تاریخ‌ها
        if ($request->filled('from_date')) {
            $query->whereDate('issued_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issued_at', '<=', $request->to_date);
        }

        $archives = $query->latest('id')->paginate($perPage);

        $archives->getCollection()->transform(function ($archive) {
            $formData = is_array($archive->form_data)
                ? $archive->form_data
                : (json_decode($archive->form_data, true) ?? []);

            $attachments = is_array($archive->attachments)
                ? $archive->attachments
                : (json_decode($archive->attachments, true) ?? []);

            // ۱. استخراج اطلاعات بیمار
            $queueItem = $formData['invoiceDetails']['updatedQueue'][0]
                ?? $formData['queueItems'][0]
                ?? $formData['patientQueue'][0]
                ?? [];
            $patientFromQueue = $queueItem['patient'] ?? [];

            $patientName = $archive->patient_name
                ?? ($patientFromQueue['full_name'] ?? null)
                ?? ($patientFromQueue['name'] ?? null)
                ?? ($formData['patient_name'] ?? null)
                ?? 'نامشخص';

            $nationalCode = $archive->national_code
                ?? ($patientFromQueue['national_code'] ?? null)
                ?? ($patientFromQueue['national_id'] ?? null)
                ?? ($formData['national_code'] ?? null)
                ?? '-';

            $mobile = $archive->mobile
                ?? ($patientFromQueue['mobile'] ?? null)
                ?? ($patientFromQueue['phone'] ?? null)
                ?? ($formData['mobile'] ?? null)
                ?? '-';

            $fileNumber = $archive->file_number
                ?? ($patientFromQueue['file_number'] ?? null)
                ?? ($patientFromQueue['case_number'] ?? null)
                ?? (string) $archive->id;

            // ۲. استخراج پزشک و خدمات
            $doctorName = $formData['doctor_name']
                ?? $formData['selectedDoctor']['name']
                ?? $queueItem['doctor']['name']
                ?? $queueItem['doctorName']
                ?? null;

            $services = [];
            $queueList = $formData['invoiceDetails']['updatedQueue']
                ?? $formData['queueItems']
                ?? (!empty($queueItem) ? [$queueItem] : []);

            foreach ($queueList as $q) {
                if (!empty($q['services']) && is_array($q['services'])) {
                    foreach ($q['services'] as $s) {
                        $sName = is_array($s) ? ($s['title'] ?? $s['name'] ?? $s['serviceTitle'] ?? 'خدمت ثبت‌شده') : (string) $s;
                        $services[] = $sName;
                    }
                }
            }

            // ۳. ساخت سوابق کاربران به صورت دقیق و انعطاف‌پذیر
            $auditLogs = [];
            $issuedAtTime = substr($archive->issued_at ?? $archive->created_at ?? date('Y-m-d H:i'), 0, 16);

            // اگر مستقیماً آرایه audit_logs در فرم ذخیره شده است
            if (!empty($formData['audit_logs']) && is_array($formData['audit_logs'])) {
                foreach ($formData['audit_logs'] as $i => $log) {
                    $log = is_array($log) ? $log : [];
                    $resolvedName = $this->resolveUserName($log, $archive, $formData);

                    $auditLogs[] = [
                        'id' => $log['id'] ?? ($i + 1),
                        'user' => ['name' => $resolvedName],
                        'created_at' => $log['created_at'] ?? $log['at'] ?? $issuedAtTime,
                        'action' => $log['action'] ?? 'اقدام ثبت‌شده',
                        'action_description' => $log['action_description'] ?? $log['description'] ?? null,
                        'badge_color' => $log['badge_color'] ?? 'blue',
                    ];
                }
            }

            // خواندن از hist_users
            $histUsers = is_array($formData['hist_users'] ?? null) ? $formData['hist_users'] : [];
            foreach ($histUsers as $i => $log) {
                $log = is_array($log) ? $log : [];
                $serviceTitles = is_array($log['serviceTitles'] ?? null) ? $log['serviceTitles'] : [];
                $resolvedName = $this->resolveUserName($log, $archive, $formData);

                $auditLogs[] = [
                    'id' => count($auditLogs) + 1,
                    'user' => ['name' => $resolvedName],
                    'created_at' => $log['at'] ?? $issuedAtTime,
                    'action' => $log['action'] ?? 'ثبت موقت خدمات',
                    'action_description' => !empty($serviceTitles)
                        ? 'خدمات ثبت‌شده: ' . implode('، ', $serviceTitles)
                        : ($log['action'] ?? 'ثبت در لیست موقت پذیرش'),
                    'badge_color' => 'amber',
                ];
            }

            // ثبت نهایی و صدور جوابدهی
            $finalSubmittedBy = is_array($formData['final_submitted_by'] ?? null) ? $formData['final_submitted_by'] : [];
            $finalUserName = $finalSubmittedBy['name']
                ?? $formData['final_submitted_name']
                ?? $archive->issued_by_name
                ?? null;

            if (!empty($finalUserName)) {
                $auditLogs[] = [
                    'id' => count($auditLogs) + 1,
                    'user' => ['name' => $finalUserName],
                    'created_at' => $finalSubmittedBy['at'] ?? $issuedAtTime,
                    'action' => 'ثبت نهایی و صدور جوابدهی',
                    'action_description' => 'پرونده نهایی ثبت، ممهور و بایگانی گردید.',
                    'badge_color' => 'emerald',
                ];
            }

            // در صورتی که هیچ لاگی یافت نشد (رکوردهای قدیمی)
            if (empty($auditLogs)) {
                $fallbackUser = !empty($archive->issued_by_name) ? $archive->issued_by_name : 'پذیرش درمانگاه';
                $auditLogs[] = [
                    'id' => 1,
                    'user' => ['name' => $fallbackUser],
                    'created_at' => $issuedAtTime,
                    'action' => 'ثبت نهایی خدمت',
                    'action_description' => !empty($services)
                        ? 'خدمات ثبت‌شده: ' . implode('، ', $services)
                        : 'خدمت ثبت و بایگانی گردید.',
                    'badge_color' => 'emerald',
                ];
            }

            // ۴. فاکتور مالی و آیتم‌های فاکتور
            $invoiceDetails = $formData['invoiceDetails'] ?? null;
            $invoice = null;

            if ($invoiceDetails || ($formData['hasInvoice'] ?? false) || !empty($formData['total_price'])) {
                $payable = $invoiceDetails['payableAmount']
                    ?? $invoiceDetails['totalPrice']
                    ?? $formData['payable_amount']
                    ?? $formData['total_price']
                    ?? 0;

                $total = $invoiceDetails['totalPrice']
                    ?? $formData['total_price']
                    ?? $payable;

                $modalQueue = $invoiceDetails['updatedQueue'] ?? $queueList;
                if (empty($modalQueue)) {
                    $modalQueue = [
                        [
                            'tempId' => $archive->id,
                            'patient' => [
                                'full_name' => $patientName,
                                'national_code' => $nationalCode,
                            ],
                            'doctor' => [
                                'name' => $doctorName ?? 'پزشک معالج',
                                'stamp_path' => $formData['doctor_stamp'] ?? $formData['selectedDoctor']['stamp_path'] ?? null,
                            ],
                            'services' => !empty($services)
                                ? array_map(function ($s) use ($total) {
                                    return ['serviceTitle' => $s, 'price' => $total];
                                }, $services)
                                : [['serviceTitle' => 'خدمات درمانی و بالینی', 'price' => $total]],
                        ]
                    ];
                }

                $invoice = [
                    'id' => $archive->id,
                    'final_amount' => (float) $payable,
                    'total_amount' => (float) $total,
                    'discount' => (float) ($invoiceDetails['discount'] ?? 0),
                    'is_paid' => true,
                    'payment_method' => 'نقدی / پوز',
                    'services' => $services,
                    'queueItems' => $modalQueue,
                ];
            }

            return [
                'id' => $archive->id,
                'patient' => [
                    'id' => $patientFromQueue['id'] ?? $archive->id,
                    'name' => $patientName,
                    'full_name' => $patientName,
                    'national_code' => $nationalCode,
                    'phone' => $mobile,
                    'mobile' => $mobile,
                    'case_number' => $fileNumber,
                    'file_number' => $fileNumber,
                ],
                'patient_name' => $patientName,
                'national_code' => $nationalCode,
                'mobile' => $mobile,
                'file_number' => $fileNumber,
                'audit_logs' => $auditLogs,
                'invoice' => $invoice,
                'form_data' => $formData,
                'attachments' => $attachments,
                'status' => 'completed',
                'created_at' => (string) ($archive->issued_at ?? $archive->created_at),
                'issued_at' => (string) ($archive->issued_at ?? $archive->created_at),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $archives,
        ]);
    }

    public function batchDelete(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        return DB::transaction(function () use ($request) {
            $deletedCount = Archive::query()
                ->whereDate('issued_at', '>=', $request->from_date)
                ->whereDate('issued_at', '<=', $request->to_date)
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'هیچ رکوردی در این بازه زمانی یافت نشد.',
                    'deleted_count' => 0,
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => "تعداد {$deletedCount} رکورد با موفقیت حذف شد.",
                'deleted_count' => $deletedCount,
            ]);
        });
    }
}
