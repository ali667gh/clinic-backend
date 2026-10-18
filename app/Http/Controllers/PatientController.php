<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
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
            'mobile'        => 'nullable|string|regex:/^09\d{9}$/',
            'address'       => 'nullable|string|max:500',
        ], [
            'full_name.required'     => 'نام و نام خانوادگی الزامی است.',
            'file_number.required'   => 'شماره پرونده الزامی است.',
            'file_number.unique'     => 'این شماره پرونده قبلاً ثبت شده است.',
            'national_code.required' => 'کد ملی الزامی است.',
            'national_code.size'     => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'national_code.unique'   => 'این کد ملی قبلاً ثبت شده است.',
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
            'mobile'        => 'nullable|string|regex:/^09\d{9}$/',
            'address'       => 'nullable|string|max:500',
        ], [
            'full_name.required'     => 'نام و نام خانوادگی الزامی است.',
            'file_number.required'   => 'شماره پرونده الزامی است.',
            'file_number.unique'     => 'این شماره پرونده متعلق به بیمار دیگری است.',
            'national_code.required' => 'کد ملی الزامی است.',
            'national_code.size'     => 'کد ملی باید ۱۰ رقم باشد.',
            'national_code.unique'   => 'این کد ملی متعلق به بیمار دیگری است.',
            'mobile.regex'           => 'شماره موبایل نامعتبر است.',
        ]);

        $patient->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'اطلاعات بیمار به‌روزرسانی شد.',
            'data'    => $patient
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
}
