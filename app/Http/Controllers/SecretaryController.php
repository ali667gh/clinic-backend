<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SecretaryController extends Controller
{
    /**
     * دریافت لیست تمام منشی‌ها / پرسنل پذیرش
     */
    public function index()
    {
        $secretaries = User::whereIn('role', ['staff_level_1', 'staff_level_2'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $secretaries
        ]);
    }

    /**
     * ثبت منشی جدید (بدون کد ملی)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullName'   => 'required|string|max:255',
            'mobile'     => 'required|string|regex:/^09\d{9}$/|unique:users,mobile',
            'userLevel'  => 'required|in:staff_level_1,staff_level_2',
            'password'   => 'nullable|string|min:6',
        ], [
            'fullName.required'  => 'نام و نام خانوادگی منشی الزامی است.',
            'mobile.required'    => 'شماره موبایل الزامی است.',
            'mobile.regex'       => 'شماره موبایل نامعتبر است (مثال: 09123456789).',
            'mobile.unique'      => 'این شماره موبایل قبلاً ثبت شده است.',
            'userLevel.required' => 'سطح دسترسی منشی الزامی است.',
            'password.min'       => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
        ]);

        // ساخت منشی؛ نام کاربری و ایمیل بر مبنای شماره موبایل ساخته می‌شوند
        $user = User::create([
            'name'        => $validated['fullName'],
            'username'    => $validated['mobile'],
            'email'       => $validated['mobile'] . '@clinic.local',
            'mobile'      => $validated['mobile'],
            'national_id' => null, // کد ملی حذف شد
            'password'    => Hash::make($request->filled('password') ? $request->password : $validated['mobile']),
            'role'        => $validated['userLevel'],
            'is_active'   => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'منشی با موفقیت ثبت شد.',
            'data'    => $user
        ], 201);
    }

    /**
     * ویرایش مشخصات منشی
     */
    public function update(Request $request, $id)
    {
        $user = User::whereIn('role', ['staff_level_1', 'staff_level_2'])->findOrFail($id);

        $validated = $request->validate([
            'fullName'   => 'required|string|max:255',
            'mobile'     => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'mobile')->ignore($user->id)],
            'userLevel'  => 'required|in:staff_level_1,staff_level_2',
            'password'   => 'nullable|string|min:6',
        ], [
            'fullName.required'  => 'نام و نام خانوادگی الزامی است.',
            'mobile.required'    => 'شماره موبایل الزامی است.',
            'mobile.regex'       => 'شماره موبایل نامعتبر است.',
            'mobile.unique'      => 'این شماره موبایل قبلاً ثبت شده است.',
            'userLevel.required' => 'سطح دسترسی الزامی است.',
        ]);

        $data = [
            'name'      => $validated['fullName'],
            'username'  => $validated['mobile'],
            'mobile'    => $validated['mobile'],
            'role'      => $validated['userLevel'],
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'اطلاعات منشی با موفقیت ویرایش شد.',
            'data'    => $user
        ]);
    }

    /**
     * حذف منشی
     */
    public function destroy($id)
    {
        $user = User::whereIn('role', ['staff_level_1', 'staff_level_2'])->findOrFail($id);
        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'منشی با موفقیت حذف شد.'
        ]);
    }
}
