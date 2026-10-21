<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    /**
     * فیلدهایی که تغییرشان بیمار را وارد لیست «مدیریت ارسالی‌ها» می‌کند.
     */
    private const TRACKED_FIELDS = ['full_name', 'file_number', 'national_code', 'mobile'];

    public function index(Request $request)
    {
        $query = Patient::query();

        if ($request->filled('search')) {
            $rawSearch = trim($request->input('search'));

            // ۱. تبدیل اعداد فارسی و عربی به انگلیسی
            $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $s = str_replace($persian, $english, $rawSearch);
            $s = str_replace($arabic, $english, $s);

            $type = $request->input('type'); // در صورت ارسال صریح از فرانت: file_number, national_code, mobile, full_name

            if ($type && in_array($type, ['file_number', 'national_code', 'mobile', 'full_name'])) {
                // اگر نوع مشخص ارسال شده بود
                $query->where($type, 'like', "%{$s}%");
            } else {
                // تفکیک هوشمند خودکار
                if (ctype_digit($s)) {
                    $len = strlen($s);

                    if (str_starts_with($s, '09') || $len === 11) {
                        // فقط شماره موبایل
                        $query->where('mobile', 'like', "%{$s}%");
                    } elseif ($len === 10) {
                        // فقط کد ملی
                        $query->where('national_code', 'like', "%{$s}%");
                    } else {
                        // اعدادی مثل 59494 فقط شماره پرونده هستند
                        $query->where('file_number', 'like', "%{$s}%");
                    }
                } else {
                    // حروف یا نام
                    $query->where('full_name', 'like', "%{$s}%");
                }
            }
        }

        $perPage = $request->input('per_page', 15);
        $patients = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $patients
        ]);
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'     => 'required|string|max:255',
            'file_number'   => 'required|string|max:50|unique:patients,file_number',
            'national_code' => 'required|string|size:10|unique:patients,national_code',
            'mobile'        => 'required|string|regex:/^09\d{9}$/', // الزامی شد و چون unique ندارد تکراری پذیرفته میشود
            'address'       => 'nullable|string|max:500',
        ], [
            'full_name.required'     => 'نام و نام خانوادگی الزامی است.',
            'file_number.required'   => 'شماره پرونده الزامی است.',
            'file_number.unique'     => 'این شماره پرونده قبلاً ثبت شده است.',
            'national_code.required' => 'کد ملی الزامی است.',
            'national_code.size'     => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'national_code.unique'   => 'این کد ملی قبلاً ثبت شده است.',
            'mobile.required'        => 'شماره موبایل الزامی است.',
            'mobile.regex'           => 'شماره موبایل وارد شده معتبر نیست (مثال: 09121234567).',
        ]);

        $patient = Patient::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'بیمار با موفقیت ثبت شد.',
            'data'    => $patient
        ], 201);
    }

    public function show($id)
    {
        $patient = Patient::findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $patient
        ]);
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'full_name'     => 'required|string|max:255',
            'file_number'   => ['required', 'string', 'max:50', Rule::unique('patients')->ignore($patient->id)],
            'national_code' => ['required', 'string', 'size:10', Rule::unique('patients')->ignore($patient->id)],
            'mobile'        => 'required|string|regex:/^09\d{9}$/', // الزامی شد
            'address'       => 'nullable|string|max:500',
        ], [
            'full_name.required'     => 'نام و نام خانوادگی الزامی است.',
            'file_number.required'   => 'شماره پرونده الزامی است.',
            'file_number.unique'     => 'این شماره پرونده متعلق به بیمار دیگری است.',
            'national_code.required' => 'کد ملی الزامی است.',
            'national_code.size'     => 'کد ملی باید ۱۰ رقم باشد.',
            'national_code.unique'   => 'این کد ملی متعلق به بیمار دیگری است.',
            'mobile.required'        => 'شماره موبایل الزامی است.',
            'mobile.regex'           => 'شماره موبایل نامعتبر است.',
        ]);

        // مقادیر قبل از ویرایش (برای تشخیص تغییر واقعی)
        $before = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $before[$field] = trim((string) $patient->{$field});
        }

        $patient->update($validated);

        // ثبت ویرایش برای بخش «مدیریت ارسالی‌ها»
        $this->trackDispatchChange($patient, $before);

        $fresh = $patient->fresh() ?? $patient;
        $fresh->makeHidden(['legacy_identifiers']);

        return response()->json([
            'status'  => 'success',
            'message' => 'اطلاعات بیمار به‌روزرسانی شد.',
            'data'    => $fresh
        ]);
    }

    public function destroy($id)
    {
        $patient = Patient::findOrFail($id);
        $patient->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'بیمار با موفقیت حذف شد.'
        ]);
    }

    /**
     * اگر یکی از فیلدهای اصلی بیمار واقعاً تغییر کرده باشد:
     *  - edited_at ثبت می‌شود تا بیمار در لیست «بیماران ویرایش‌شده» بیاید
     *  - کد ملی / شماره پرونده‌ی قبلی نگهداری می‌شود تا پرونده‌های بایگانی قدیمی پیدا شوند
     *
     * خطا در این بخش هرگز نباید ویرایش خود بیمار را خراب کند.
     */
    private function trackDispatchChange(Patient $patient, array $before): void
    {
        try {
            $changed = [];
            foreach (self::TRACKED_FIELDS as $field) {
                if ($before[$field] !== trim((string) $patient->{$field})) {
                    $changed[] = $field;
                }
            }

            if (empty($changed)) {
                return;
            }

            $legacy = $this->decodeLegacy($patient->getAttribute('legacy_identifiers'));

            if (in_array('national_code', $changed, true) && $before['national_code'] !== '') {
                $legacy['national_codes'][] = $before['national_code'];
            }

            if (in_array('file_number', $changed, true) && $before['file_number'] !== '') {
                $legacy['file_numbers'][] = $before['file_number'];
            }

            $legacy['national_codes'] = array_values(array_unique($legacy['national_codes']));
            $legacy['file_numbers']   = array_values(array_unique($legacy['file_numbers']));

            Patient::query()->whereKey($patient->getKey())->update([
                'edited_at'          => now(),
                'legacy_identifiers' => json_encode($legacy, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Dispatch tracking failed for patient ' . $patient->getKey() . ': ' . $e->getMessage());
        }
    }

    private function decodeLegacy($raw): array
    {
        $data = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

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
