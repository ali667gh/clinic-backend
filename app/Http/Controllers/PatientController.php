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
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('national_code', 'like', "%{$s}%")
                    ->orWhere('file_number', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        // مرتب‌سازی بر اساس جدیدترین‌ها
        $patients = $query->latest()->paginate(15);

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
