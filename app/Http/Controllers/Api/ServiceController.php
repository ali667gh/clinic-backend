<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    // لیست خدمات درختی
    public function index(Request $request)
    {
        $query = Service::query()->whereNull('parent_id')->with(['children']);

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)->with(['children' => function ($q) {
                $q->where('is_active', true);
            }]);
        }

        $services = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $services
        ]);
    }

    // ثبت خدمت جدید (والد یا فرزند)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id'      => 'nullable|exists:services,id',
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50|unique:services,code',
            'price'          => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'is_visit'       => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
            'has_signature'  => 'nullable|boolean',
            // مختصات بر حسب میلی‌متر؛ مبدأ: گوشه بالا-چپ صفحه A4 (۲۱۰×۲۹۷)
            'signature_x'    => 'nullable|numeric|min:0|max:210',
            'signature_y'    => 'nullable|numeric|min:0|max:297',
            'signature_page' => 'nullable|string|in:first,last',
            // پیوست فایل برای این خدمت در پنل جوابدهی اجباری است
            'requires_attachment' => 'nullable|boolean',
        ], [
            'name.required'   => 'نام خدمت الزامی است.',
            'code.unique'     => 'این کد خدمت قبلاً ثبت شده است.',
            'signature_x.max' => 'موقعیت افقی مهر باید حداکثر ۲۱۰ میلی‌متر باشد.',
            'signature_y.max' => 'موقعیت عمودی مهر باید حداکثر ۲۹۷ میلی‌متر باشد.',
        ]);

        $service = Service::create([
            'parent_id'  => $validated['parent_id'] ?? null,
            'name'       => trim($validated['name']),
            'code'       => !empty($validated['code']) ? trim($validated['code']) : null,
            'price'      => $validated['price'] ?? 0,
            'is_active'  => $validated['is_active'] ?? true,
            'is_visit'   => $validated['is_visit'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            // فیلدهای مهر و امضا
            'has_signature'  => $request->boolean('has_signature'),
            'signature_x'    => $request->filled('signature_x') ? (float) $request->input('signature_x') : null,
            'signature_y'    => $request->filled('signature_y') ? (float) $request->input('signature_y') : null,
            'signature_page' => $validated['signature_page'] ?? 'last',
            // پیوست اجباری
            'requires_attachment' => $request->boolean('requires_attachment'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'خدمت با موفقیت ثبت شد.',
            'data'    => $service->load('children')
        ], 201);
    }

    // به‌روزرسانی خدمت
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'parent_id'      => ['nullable', 'exists:services,id', Rule::notIn([$service->id])],
            'name'           => 'required|string|max:255',
            'code'           => ['nullable', 'string', 'max:50', Rule::unique('services', 'code')->ignore($service->id)],
            'price'          => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
            'is_visit'       => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
            'has_signature'  => 'nullable|boolean',
            // مختصات بر حسب میلی‌متر؛ مبدأ: گوشه بالا-چپ صفحه A4 (۲۱۰×۲۹۷)
            'signature_x'    => 'nullable|numeric|min:0|max:210',
            'signature_y'    => 'nullable|numeric|min:0|max:297',
            'signature_page' => 'nullable|string|in:first,last',
            // پیوست فایل برای این خدمت در پنل جوابدهی اجباری است
            'requires_attachment' => 'nullable|boolean',
        ], [
            'name.required'   => 'نام خدمت الزامی است.',
            'code.unique'     => 'این کد خدمت قبلاً ثبت شده است.',
            'signature_x.max' => 'موقعیت افقی مهر باید حداکثر ۲۱۰ میلی‌متر باشد.',
            'signature_y.max' => 'موقعیت عمودی مهر باید حداکثر ۲۹۷ میلی‌متر باشد.',
        ]);

        $service->update([
            'parent_id'  => $validated['parent_id'] ?? null,
            'name'       => trim($validated['name']),
            'code'       => !empty($validated['code']) ? trim($validated['code']) : null,
            'price'      => $validated['price'] ?? 0,
            'is_active'  => $validated['is_active'] ?? $service->is_active,
            'is_visit'   => $validated['is_visit'] ?? $service->is_visit,
            'sort_order' => $validated['sort_order'] ?? $service->sort_order,
            // فیلدهای مهر و امضا
            'has_signature'  => $request->boolean('has_signature'),
            'signature_x'    => $request->filled('signature_x') ? (float) $request->input('signature_x') : null,
            'signature_y'    => $request->filled('signature_y') ? (float) $request->input('signature_y') : null,
            'signature_page' => $validated['signature_page'] ?? 'last',
            // پیوست اجباری
            'requires_attachment' => $request->boolean('requires_attachment'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'خدمت با موفقیت ویرایش شد.',
            'data'    => $service
        ]);
    }

    // حذف خدمت
    public function destroy($id)
    {
        $service = Service::findOrFail($id);

        // حذف فایل سربرگ خدمت و زیرخدمت‌ها از دیسک
        foreach ($service->children as $child) {
            $this->removeHeaderFile($child);
        }
        $this->removeHeaderFile($service);

        $service->children()->delete();
        $service->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'خدمت حذف گردید.'
        ]);
    }

    // آپلود یا جایگزینی تصویر سربرگ خدمت
    public function uploadHeader(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'header' => 'required|file|mimes:png,jpg,jpeg|max:5120',
        ], [
            'header.required' => 'تصویر سربرگ الزامی است.',
            'header.mimes'    => 'سربرگ باید تصویر PNG یا JPG باشد.',
            'header.max'      => 'حجم تصویر سربرگ نباید بیشتر از ۵ مگابایت باشد.',
            'header.file'     => 'فایل سربرگ نامعتبر است.',
        ]);

        // حذف تصویر قبلی
        $this->removeHeaderFile($service);

        $path = $request->file('header')->store('service-headers', 'public');
        $service->update(['header_path' => $path]);

        return response()->json([
            'status'  => 'success',
            'message' => 'سربرگ خدمت با موفقیت ذخیره شد.',
            'data'    => $service->fresh(),
        ]);
    }

    // حذف تصویر سربرگ خدمت
    public function deleteHeader($id)
    {
        $service = Service::findOrFail($id);

        $this->removeHeaderFile($service);
        $service->update(['header_path' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'سربرگ خدمت حذف شد.',
            'data'    => $service->fresh(),
        ]);
    }

    // نمایش تصویر سربرگ (برای پیش‌نمایش و درج روی PDF در فرانت‌اند)
    public function showHeader($id)
    {
        $service = Service::findOrFail($id);
        $path = $service->getAttributes()['header_path'] ?? null;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'سربرگی برای این خدمت ثبت نشده است.'], 404);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // حذف فایل فیزیکی سربرگ از دیسک (بدون تغییر در دیتابیس)
    private function removeHeaderFile(Service $service): void
    {
        $path = $service->getAttributes()['header_path'] ?? null;

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // تغییر وضعیت فعال / غیرفعال
    public function toggleStatus($id)
    {
        $service = Service::findOrFail($id);
        $service->is_active = !$service->is_active;
        $service->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'وضعیت خدمت به‌روز شد.',
            'data'    => $service
        ]);
    }
}
