<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SignatureStampService
{
    /**
     * درج مهر پزشک روی فایل تصویر پیوست
     *
     * @param UploadedFile $file
     * @param string|null $stampRelativePath مسیر فایل مهر در دیسک public یا مسیر کامل
     * @param string $saveDirectory
     * @return string مسیر نسبی فایل ذخیره شده در دیسک public
     */
    public function applyStampAndSave(UploadedFile $file, ?string $stampRelativePath, string $saveDirectory = 'attachments'): string
    {
        $mime = strtolower((string) $file->getClientMimeType());
        $validImageMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

        // ۱. اگر فایل تصویری نیست (مثلاً PDF است)
        if (!in_array($mime, $validImageMimes, true)) {
            Log::info("SignatureStamp: File is not an image ({$mime}). Storing original file.");
            return $file->store($saveDirectory, 'public');
        }

        // ۲. اگر مسیر مهر ارسال نشده
        if (empty($stampRelativePath)) {
            Log::warning("SignatureStamp: No stamp path provided for image: " . $file->getClientOriginalName());
            return $file->store($saveDirectory, 'public');
        }

        // ۳. پاکسازی و پیدا کردن مسیر فیزیکی مهر
        $resolvedStampFullPath = $this->resolveStampFullPath($stampRelativePath);

        if (!$resolvedStampFullPath || !file_exists($resolvedStampFullPath)) {
            Log::warning("SignatureStamp: Stamp file does not exist on disk: " . $stampRelativePath);
            return $file->store($saveDirectory, 'public');
        }

        $sourcePath = $file->getRealPath();

        // ۴. لود تصویر اصلی
        $mainImage = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$mainImage) {
            Log::error("SignatureStamp: Failed to create image from source file.");
            return $file->store($saveDirectory, 'public');
        }

        // ۵. لود تصویر مهر
        $stampInfo = @getimagesize($resolvedStampFullPath);
        $stampMime = $stampInfo['mime'] ?? '';
        $stampImage = match ($stampMime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($resolvedStampFullPath),
            'image/png' => @imagecreatefrompng($resolvedStampFullPath),
            'image/webp' => @imagecreatefromwebp($resolvedStampFullPath),
            default => null,
        };

        if (!$stampImage) {
            Log::error("SignatureStamp: Failed to load stamp image from: " . $resolvedStampFullPath);
            imagedestroy($mainImage);
            return $file->store($saveDirectory, 'public');
        }

        // ۶. محاسبه ابعاد متناسب مهر (۲۰٪ تا ۲۵٪ عرض تصویر اصلی)
        $mainWidth = imagesx($mainImage);
        $mainHeight = imagesy($mainImage);
        $stampWidth = imagesx($stampImage);
        $stampHeight = imagesy($stampImage);

        $targetStampWidth = (int) max(140, min($mainWidth * 0.25, 400));
        $targetStampHeight = (int) ($stampHeight * ($targetStampWidth / max(1, $stampWidth)));

        // ایجاد بستر مهر با حفظ کانال آلفا (ترنسپرنسی)
        $resizedStamp = imagecreatetruecolor($targetStampWidth, $targetStampHeight);
        imagealphablending($resizedStamp, false);
        imagesavealpha($resizedStamp, true);
        $transparent = imagecolorallocatealpha($resizedStamp, 0, 0, 0, 127);
        imagefilledrectangle($resizedStamp, 0, 0, $targetStampWidth, $targetStampHeight, $transparent);
        imagecopyresampled($resizedStamp, $stampImage, 0, 0, 0, 0, $targetStampWidth, $targetStampHeight, $stampWidth, $stampHeight);

        // ۷. موقعیت درج: گوشه پایین سمت چپ با مارجین ۳٪
        $margin = (int) max(20, $mainWidth * 0.03);
        $destX = $margin;
        $destY = max(0, $mainHeight - $targetStampHeight - $margin);

        // ادغام مهر روی تصویر اصلی
        imagealphablending($mainImage, true);
        imagesavealpha($mainImage, true);
        imagecopy($mainImage, $resizedStamp, $destX, $destY, 0, 0, $targetStampWidth, $targetStampHeight);

        // ۸. آماده‌سازی نام و ذخیره فایل نهایی
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = Str::random(40) . '.' . $extension;
        $relativeDestPath = trim($saveDirectory, '/') . '/' . $fileName;
        $absoluteDestPath = Storage::disk('public')->path($relativeDestPath);

        $dir = dirname($absoluteDestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // خروجی نهایی متناسب با فرمت
        match ($mime) {
            'image/png' => imagepng($mainImage, $absoluteDestPath, 8),
            'image/webp' => imagewebp($mainImage, $absoluteDestPath, 90),
            default => imagejpeg($mainImage, $absoluteDestPath, 90),
        };

        // آزادسازی حافظه رم
        imagedestroy($mainImage);
        imagedestroy($stampImage);
        imagedestroy($resizedStamp);

        Log::info("SignatureStamp: Successfully applied stamp to {$relativeDestPath}");

        return $relativeDestPath;
    }

    /**
     * پیدا کردن مسیر فیزیکی فایل مهر از بین احتمالات مختلف
     */
    private function resolveStampFullPath(string $path): ?string
    {
        // پاکسازی مسیر
        $clean = ltrim(str_replace(['/storage/', 'storage/', 'public/'], '', $path), '/\\');

        $candidates = [
            storage_path('app/public/' . $clean), // مسیر استاندارد در لاراول داکر/سرور
            public_path('storage/' . $clean),
            storage_path('app/' . $clean),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                Log::info("Stamp found at: " . $candidate); // برای دیباگ خودت
                return $candidate;
            }
        }

        Log::error("Stamp NOT FOUND for path: " . $path);
        return null;
    }
}
