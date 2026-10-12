<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ورود کاربر با شماره موبایل و رمز عبور
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'password' => ['required', 'string'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex' => 'فرمت شماره موبایل نامعتبر است (مثال: 09123456789).',
            'password.required' => 'رمز عبور الزامی است.',
        ]);

        $user = User::where('mobile', $credentials['mobile'])->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'mobile' => ['شماره موبایل یا رمز عبور اشتباه است.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'حساب کاربری شما غیرفعال است. با مدیر سیستم تماس بگیرید.',
            ], 403);
        }

        // ورود تک‌دستگاهی (باطل کردن توکن‌های قبلی)
        $user->tokens()->delete();

        $token = $user->createToken('clinic-panel')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'خوش آمدید ' . $user->name,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'mobile' => $user->mobile,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ],
            ],
        ]);
    }

    /**
     * خروج کاربر
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'با موفقیت خارج شدید.',
        ]);
    }

    /**
     * اطلاعات کاربر لاگین‌شده
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user(),
        ]);
    }
}
