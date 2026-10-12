<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Doctor;
use App\Models\Service;
use App\Services\SignatureStampService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ArchiveController extends Controller
{
    protected SignatureStampService $stampService;
    protected SmsService $smsService;

    public function __construct(SignatureStampService $stampService, SmsService $smsService)
    {
        $this->stampService = $stampService;
        $this->smsService = $smsService;
    }

    // =========================================================================
    // ۱. متدهای پیش‌نویس‌های آزمایشگاه (Lab Drafts)
    // =========================================================================

    private function draftPayload($draft): array
    {
        $services = collect(json_decode($draft->services, true) ?: [])
            ->map(function ($s) {
                if (!empty($s['file'])) {
                    $s['file'] = [
                        'name' => $s['file']['name'] ?? null,
                        'size' => $s['file']['size'] ?? null,
                        'mime' => $s['file']['mime'] ?? null,
                    ];
                }
                return $s;
            })
            ->values()
            ->all();

        $creatorUser = !empty($draft->user_id) ? DB::table('users')->where('id', $draft->user_id)->first() : null;
        $creatorName = $creatorUser?->name ?? 'کاربر پذیرش';

        return [
            'id'         => $draft->id,
            'patient_id' => (int) $draft->patient_id,
            'user_id'    => $draft->user_id ? (int) $draft->user_id : null,
            'user_name'  => $creatorName,
            'doctor_id'  => $draft->doctor_id ? (int) $draft->doctor_id : null,
            'services'   => $services,
            'created_at' => Carbon::parse($draft->created_at)->toIso8601String(),
        ];
    }

    public function draftIndex(Request $request)
    {
        $patientId = $request->query('patient_id', $request->input('patient_id'));

        if (!$patientId) {
            return response()->json(['data' => []]);
        }

        $drafts = DB::table('lab_drafts')
            ->where('patient_id', $patientId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn($d) => $this->draftPayload($d));

        return response()->json(['data' => $drafts]);
    }

    public function draftStore(Request $request)
    {
        $data = $request->validate([
            'patient_id'     => 'required|exists:patients,id',
            'doctor_id'      => 'nullable|integer',
            'services'       => 'required',
            'files'          => 'nullable|array',
            'files.*'        => 'file|mimes:pdf,jpg,jpeg,png|max:20480',
            'file_indexes'   => 'nullable|array',
            'file_indexes.*' => 'integer',
        ]);

        $services = is_array($data['services'])
            ? $data['services']
            : json_decode($data['services'], true);

        if (!is_array($services)) {
            return response()->json(['message' => 'فرمت داده‌های خدمات نامعتبر است.'], 422);
        }
        $services = array_values($services);

        foreach ($services as $i => $s) {
            if (!isset($services[$i]['file'])) {
                $services[$i]['file'] = null;
            }
        }

        $files = $request->file('files', []);
        $indexes = $request->input('file_indexes', []);

        foreach ($files as $n => $file) {
            $idx = (int) ($indexes[$n] ?? -1);
            if (!array_key_exists($idx, $services)) {
                continue;
            }
            $services[$idx]['file'] = [
                'path' => $file->store("lab-drafts/{$data['patient_id']}", 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }

        $userId = Auth::id() ?? ($request->user() ? $request->user()->id : $request->input('user_id'));

        $id = DB::table('lab_drafts')->insertGetId([
            'patient_id' => $data['patient_id'],
            'user_id'    => $userId,
            'doctor_id'  => $data['doctor_id'] ?? null,
            'services'   => json_encode($services, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draft = DB::table('lab_drafts')->find($id);

        return response()->json(['data' => $this->draftPayload($draft)], 201);
    }

    public function draftFile(int $id, int $index)
    {
        $draft = DB::table('lab_drafts')->find($id);
        abort_unless($draft, 404, 'پیش‌نویس مورد نظر یافت نشد.');

        $services = json_decode($draft->services, true) ?: [];
        $file = $services[$index]['file'] ?? null;

        abort_unless($file && Storage::disk('local')->exists($file['path']), 404, 'فایل فیزیکی در سرور یافت نشد.');

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    public function draftDownloadFile(int $id, int $fileIndex)
    {
        $draft = DB::table('lab_drafts')->where('id', $id)->first();
        if (!$draft) {
            return response()->json(['message' => 'پیش‌نویس مورد نظر یافت نشد.'], 404);
        }

        $services = json_decode($draft->services, true) ?: [];
        $file = $services[$fileIndex]['file'] ?? null;

        if (!$file) {
            return response()->json(['message' => 'اطلاعات فایل در پیش‌نویس یافت نشد.'], 404);
        }

        $filePath = is_array($file) ? ($file['path'] ?? null) : $file;
        $fileName = is_array($file) ? ($file['name'] ?? 'attachment.pdf') : 'attachment.pdf';

        if (!$filePath) {
            return response()->json(['message' => 'مسیر فایل نامعتبر است.'], 404);
        }

        $cleanPath = ltrim(str_replace(['/storage/', 'storage/', 'public/'], '', $filePath), '/\\');

        if (Storage::disk('local')->exists($cleanPath)) {
            return Storage::disk('local')->download($cleanPath, $fileName);
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->download($cleanPath, $fileName);
        }

        if (file_exists(storage_path('app/' . $cleanPath))) {
            return response()->download(storage_path('app/' . $cleanPath), $fileName);
        }

        if (file_exists(storage_path('app/public/' . $cleanPath))) {
            return response()->download(storage_path('app/public/' . $cleanPath), $fileName);
        }

        Log::error("Lab draft physical file not found: {$cleanPath} for draft ID: {$id}");

        return response()->json(['message' => 'فایل فیزیکی در سرور یافت نشد.'], 404);
    }

    public function draftDestroy(int $id)
    {
        $draft = DB::table('lab_drafts')->find($id);
        abort_unless($draft, 404, 'پیش‌نویس یافت نشد.');

        foreach (json_decode($draft->services, true) ?: [] as $s) {
            if (!empty($s['file']['path']) && Storage::disk('local')->exists($s['file']['path'])) {
                Storage::disk('local')->delete($s['file']['path']);
            }
        }

        DB::table('lab_drafts')->where('id', $id)->delete();

        return response()->json(['message' => 'پیش‌نویس با موفقیت حذف شد.']);
    }

    // =========================================================================
    // ۲. متدهای بایگانی و آرشیو (Archive Management)
    // =========================================================================

    public function index(Request $request)
    {
        $query = Archive::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('patient_name', 'like', "%{$s}%")
                    ->orWhere('national_code', 'like', "%{$s}%")
                    ->orWhere('file_number', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('issued_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('issued_at', '<=', $request->to_date);
        }

        return response()->json(
            $query->orderByDesc('issued_at')
                ->paginate($request->get('per_page', 10))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_name'      => 'required|string|max:255',
            'national_code'     => 'required|string|max:20',
            'file_number'       => 'nullable|string|max:50',
            'mobile'            => 'nullable|string|max:20',
            'issued_at'         => 'required|date',
            'form_data'         => 'nullable',
            'created_by_name'   => 'nullable|string',
            'issued_by_name'    => 'nullable|string',
            'issued_by'         => 'nullable|integer',
            'files'             => 'nullable|array',
            'files.*'           => 'file|max:20480',
            'file_doctors'      => 'nullable|array',
            'file_doctors.*'    => 'nullable|integer',
            // شناسه خدمتِ هر فایل (هم‌اندیس با files[])؛ برای بررسی «پیوست اجباری»
            'file_services'     => 'nullable|array',
            'file_services.*'   => 'nullable|string|max:100',
        ]);

        $formData = $request->input('form_data');
        if (is_string($formData)) {
            $formData = json_decode($formData, true) ?: [];
        }

        // بررسی خدماتی که پیوست فایل برای آن‌ها اجباری است (قبل از ذخیره هر فایلی)
        $missingRequired = $this->findMissingRequiredAttachments($request, $formData);
        if (!empty($missingRequired)) {
            return response()->json([
                'message' => 'برای این خدمت نیاز به فایل ضمیمه است: «' . implode('»، «', $missingRequired) . '»',
                'errors'  => ['files' => ['برای این خدمت نیاز به فایل ضمیمه است.']],
                'missing_services' => array_values($missingRequired),
            ], 422);
        }

        $issuedAt = Carbon::parse($validated['issued_at']);
        $issuedAt->setTime(now()->hour, now()->minute, now()->second);

        $fileDoctors = $request->input('file_doctors', []);
        $attachmentPaths = [];

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            foreach ($files as $index => $file) {
                $doctorId = $fileDoctors[$index] ?? null;
                $stampPath = null;

                if ($doctorId) {
                    $doctor = Doctor::find($doctorId);
                    $stampPath = $doctor?->stamp_path;
                }

                // نکته: مهر روی فایل‌های PDF در فرانت‌اند (و بر اساس تنظیمات خدمت) زده می‌شود.
                // این سرویس فقط روی فایل‌های تصویری مهر پزشک را درج می‌کند و PDF را دست‌نخورده ذخیره می‌کند.
                $saveDirectory = 'archives/' . $validated['national_code'];
                $path = $this->stampService->applyStampAndSave($file, $stampPath, $saveDirectory);

                $attachmentPaths[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $file->getClientMimeType(),
                    'size'          => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : $file->getSize(),
                    'doctor_id'     => $doctorId,
                ];
            }
        }

        $patientMobile = $validated['mobile']
            ?? ($formData['mobile'] ?? null)
            ?? ($formData['phone'] ?? null)
            ?? ($formData['patient']['mobile'] ?? null)
            ?? ($formData['patient']['phone'] ?? null);

        $user = Auth::user() ?? $request->user();

        $creatorName = $user?->name
            ?? $request->input('issued_by_name')
            ?? $request->input('created_by_name')
            ?? ($formData['current_user_name'] ?? null)
            ?? ($formData['submitted_by'] ?? null)
            ?? ($formData['userName'] ?? null)
            ?? ($formData['user']['name'] ?? null)
            ?? 'کاربر پذیرش';

        $issuerId = $user?->id ?? $request->input('issued_by');
        $trackingToken = Str::random(32) . dechex(time());

        $initialHistory = [];
        $logId = 1;

        if (!empty($formData['history']) && is_array($formData['history'])) {
            foreach ($formData['history'] as $item) {
                $rawName = $item['user_name'] ?? ($item['user']['name'] ?? null);
                $finalName = (!empty($rawName) && $rawName !== 'کاربر سیستم') ? $rawName : $creatorName;

                $initialHistory[] = [
                    'id'                 => $logId++,
                    'user'               => ['name' => $finalName],
                    'user_name'          => $finalName,
                    'action'             => $item['action'] ?? 'عملیات در سیستم',
                    'action_description' => $item['action_description'] ?? ($item['action'] ?? ''),
                    'created_at'         => $item['created_at'] ?? now()->toIso8601String(),
                    'at'                 => $item['created_at'] ?? now()->toIso8601String(),
                ];
            }
        }

        if (empty($initialHistory)) {
            $initialHistory[] = [
                'id'                 => $logId++,
                'user'               => ['name' => $creatorName],
                'user_name'          => $creatorName,
                'action'             => 'ثبت اقلام در لیست موقت',
                'action_description' => 'اقلام و خدمات بیمار در لیست موقت آزمایشگاه ثبت شد.',
                'created_at'         => now()->subMinutes(2)->toIso8601String(),
            ];

            $initialHistory[] = [
                'id'                 => $logId++,
                'user'               => ['name' => $creatorName],
                'user_name'          => $creatorName,
                'action'             => 'صدور صورتحساب و پیش‌فاکتور',
                'action_description' => 'پیش‌فاکتور و تسویه خدمات ثبت‌شده محاسبه و صادر گردید.',
                'created_at'         => now()->subMinute()->toIso8601String(),
            ];

            $initialHistory[] = [
                'id'                 => $logId++,
                'user'               => ['name' => $creatorName],
                'user_name'          => $creatorName,
                'action'             => 'ثبت نهایی و صدور جوابدهی',
                'action_description' => 'پرونده نهایی ثبت، ممهور به مهر پزشک و بایگانی گردید.',
                'created_at'         => now()->toIso8601String(),
            ];
        }

        $archive = Archive::create([
            'tracking_token' => $trackingToken,
            'patient_name'   => $validated['patient_name'],
            'national_code'  => $validated['national_code'],
            'file_number'    => $validated['file_number'] ?? null,
            'mobile'         => $patientMobile,
            'issued_by'      => $issuerId,
            'issued_by_name' => $creatorName,
            'issued_at'      => $issuedAt,
            'form_data'      => $formData,
            'attachments'    => $attachmentPaths,
            'history'        => $initialHistory,
        ]);

        if (!empty($patientMobile)) {
            try {
                $this->smsService->sendResultLink(
                    $patientMobile,
                    $validated['patient_name'],
                    $trackingToken
                );
            } catch (\Throwable $e) {
                Log::error("خطا در ارسال پیامک جوابدهی: " . $e->getMessage(), [
                    'mobile'        => $patientMobile,
                    'patient_name'  => $validated['patient_name'],
                    'archive_id'    => $archive->id,
                ]);
            }
        }

        return response()->json([
            'message' => 'پرونده جوابدهی با موفقیت در سیستم بایگانی و ممهور شد و پیامک برای بیمار ارسال گردید.',
            'data'    => $archive,
        ], 201);
    }

    public function notifyPatient($archiveId)
    {
        $archive = Archive::findOrFail($archiveId);

        $formData = is_array($archive->form_data)
            ? $archive->form_data
            : (json_decode($archive->form_data ?? '{}', true) ?: []);

        $mobile = $archive->mobile
            ?? ($formData['mobile'] ?? null)
            ?? ($formData['phone'] ?? null)
            ?? ($formData['patient']['mobile'] ?? null)
            ?? ($formData['patient']['phone'] ?? null);

        if (empty($mobile)) {
            return response()->json(['message' => 'شماره موبایلی برای این پرونده ثبت نشده است.'], 422);
        }

        $sent = $this->smsService->sendResultLink(
            $mobile,
            $archive->patient_name,
            $archive->tracking_token
        );

        if ($sent) {
            $this->addToHistory($archive, 'ارسال پیامک', "پیامک حاوی لینک پرتال مجدداً به شماره {$mobile} ارسال شد.");
            return response()->json(['message' => 'پیامک لینک پرتال با موفقیت برای بیمار ارسال شد.']);
        }

        return response()->json(['message' => 'خطا در ارسال پیامک. لطفاً لاگ‌های سرور را بررسی نمایید.'], 500);
    }

    public function show($archive)
    {
        $model = $archive instanceof Archive ? $archive : Archive::findOrFail($archive);
        return response()->json($model);
    }

    public function destroy($archive)
    {
        $model = $archive instanceof Archive ? $archive : Archive::findOrFail($archive);

        if ($model->attachments) {
            $attachments = is_array($model->attachments)
                ? $model->attachments
                : json_decode($model->attachments, true);

            if (is_array($attachments)) {
                foreach ($attachments as $item) {
                    $path = is_array($item) ? ($item['path'] ?? null) : $item;
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
        }

        $model->delete();

        return response()->json([
            'message' => 'رکورد بایگانی با موفقیت حذف شد.'
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $archives = Archive::whereDate('issued_at', '>=', $request->from_date)
            ->whereDate('issued_at', '<=', $request->to_date)->get();

        foreach ($archives as $archive) {
            if ($archive->attachments) {
                $attachments = is_array($archive->attachments)
                    ? $archive->attachments
                    : json_decode($archive->attachments, true);

                if (is_array($attachments)) {
                    foreach ($attachments as $item) {
                        $path = is_array($item) ? ($item['path'] ?? null) : $item;
                        if ($path && Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                }
            }
            $archive->delete();
        }

        return response()->json([
            'message' => "{$archives->count()} رکورد بایگانی حذف شد.",
            'deleted_count' => $archives->count(),
        ]);
    }

    public function downloadAttachment($id, $index)
    {
        $archive = Archive::findOrFail($id);
        $attachments = $archive->attachments ?? [];

        if (!is_array($attachments) || !isset($attachments[$index])) {
            return response()->json(['message' => 'پیوست مورد نظر یافت نشد.'], 404);
        }

        $rawTarget = $attachments[$index];
        $filename = is_array($rawTarget)
            ? ($rawTarget['path'] ?? $rawTarget['file'] ?? $rawTarget['name'] ?? '')
            : (string) $rawTarget;

        // تمیزکاری نام فایل
        $cleanFilename = basename($filename);
        $cleanFilename = preg_replace('/^\d+_/', '', $cleanFilename);

        $nationalCode = $archive->national_code ?? $archive->patient_national_code ?? null;

        // مسیرهای احتمالی جستجو
        $candidatePaths = [
            storage_path('app/public/' . ltrim($filename, '/')),
            storage_path('app/' . ltrim($filename, '/')),
            public_path('storage/' . ltrim($filename, '/')),
            public_path(ltrim($filename, '/')),
        ];

        if ($nationalCode) {
            $candidatePaths[] = storage_path('app/public/archives/' . $nationalCode . '/' . basename($filename));
            $candidatePaths[] = storage_path('app/public/attachments/' . $nationalCode . '/' . basename($filename));
            $candidatePaths[] = storage_path('app/archives/' . $nationalCode . '/' . basename($filename));
            $candidatePaths[] = storage_path('app/' . $nationalCode . '/' . basename($filename));
        }

        $foundPath = null;
        foreach ($candidatePaths as $path) {
            if (file_exists($path) && is_file($path)) {
                $foundPath = $path;
                break;
            }
        }

        // اگر پیدا نشد، جستجوی عمیق در پوشه‌های ذخیره‌سازی
        if (!$foundPath) {
            $searchDirs = [
                storage_path('app/public'),
                storage_path('app'),
            ];
            foreach ($searchDirs as $dir) {
                if (is_dir($dir)) {
                    $files = File::allFiles($dir);
                    foreach ($files as $file) {
                        $fName = $file->getFilename();
                        if ($fName === basename($filename) || $fName === $cleanFilename || Str::endsWith($file->getPathname(), basename($filename))) {
                            $foundPath = $file->getPathname();
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$foundPath || !file_exists($foundPath)) {
            Log::error('Attachment file not found:', [
                'archive_id' => $id,
                'index' => $index,
                'raw_attachment' => $rawTarget,
                'filename' => $filename,
                'checked_paths' => $candidatePaths
            ]);
            return response()->json([
                'message' => 'فایل فیزیکی روی سرور یافت نشد.',
                'debug' => [
                    'filename' => $filename,
                    'national_code' => $nationalCode
                ]
            ], 404);
        }

        $mimeType = File::mimeType($foundPath) ?: 'application/octet-stream';
        $downloadName = basename($foundPath);

        // فایل همان‌طور که ذخیره شده (از قبل ممهور) دانلود می‌شود.
        return response()->download($foundPath, $downloadName, [
            'Content-Type' => $mimeType,
            'Access-Control-Expose-Headers' => 'Content-Disposition'
        ]);
    }


    // =========================================================================
    // ۳. متدهای پرتال مراجعین (Patient Portal)
    // =========================================================================

    public function portalShow($token)
    {
        $archive = Archive::where('tracking_token', $token)->first();

        if (!$archive) {
            return response()->json([
                'message' => 'اطلاعات پرونده یافت نشد یا لینک منقضی شده است.'
            ], 404);
        }

        $attachments = is_array($archive->attachments)
            ? $archive->attachments
            : json_decode($archive->attachments ?? '[]', true);

        $formattedAttachments = [];
        if (is_array($attachments)) {
            foreach ($attachments as $idx => $item) {
                $formattedAttachments[] = [
                    'index'         => $idx,
                    'original_name' => is_array($item) ? ($item['original_name'] ?? 'فایل جوابدهی') : 'فایل جوابدهی',
                    'url'           => url("/api/portal/{$token}/attachments/{$idx}"),
                    'mime_type'     => is_array($item) ? ($item['mime_type'] ?? '') : '',
                    'size'          => is_array($item) ? ($item['size'] ?? 0) : 0,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'patient_name'  => $archive->patient_name,
                'national_code' => $archive->national_code,
                'file_number'   => $archive->file_number,
                'issued_at'     => $archive->issued_at,
                'form_data'     => is_array($archive->form_data) ? $archive->form_data : json_decode($archive->form_data ?? '{}', true),
                'attachments'   => $formattedAttachments,
            ]
        ]);
    }

    public function portalDownloadAttachment(string $token, int $index)
    {
        $archive = Archive::where('tracking_token', $token)->firstOrFail();

        $attachments = is_array($archive->attachments)
            ? $archive->attachments
            : json_decode($archive->attachments ?? '[]', true);

        if (!isset($attachments[$index])) {
            abort(404, 'فایل پیوست در سیستم یافت نشد.');
        }

        $attachment = $attachments[$index];
        $rawPath = is_array($attachment) ? ($attachment['path'] ?? '') : (string)$attachment;
        $originalName = is_array($attachment) ? ($attachment['original_name'] ?? 'attachment.pdf') : 'attachment.pdf';
        $natCode = trim((string)($archive->national_code ?? ''));

        $filePath = null;

        if ($natCode !== '') {
            $folderCandidates = [
                storage_path('app/public/attachments/' . $natCode),
                storage_path('app/attachments/' . $natCode),
                storage_path('app/public/archives/' . $natCode),
                public_path('storage/attachments/' . $natCode),
                public_path('storage/archives/' . $natCode),
            ];

            foreach ($folderCandidates as $dir) {
                if (is_dir($dir)) {
                    $scannedFiles = array_values(array_filter(glob($dir . '/*'), function ($f) {
                        return is_file($f) && filesize($f) > 0;
                    }));

                    if (!empty($scannedFiles)) {
                        if (!empty($rawPath)) {
                            foreach ($scannedFiles as $sf) {
                                if (str_contains(basename($sf), basename($rawPath))) {
                                    $filePath = $sf;
                                    break;
                                }
                            }
                        }
                        if (!$filePath && isset($scannedFiles[$index])) {
                            $filePath = $scannedFiles[$index];
                        }
                    }
                }
                if ($filePath) {
                    break;
                }
            }
        }

        if (!$filePath) {
            $cleanRaw = ltrim(str_replace(['/storage/', 'storage/', 'public/'], '', $rawPath), '/\\');
            $possiblePaths = [
                storage_path('app/public/' . $cleanRaw),
                storage_path('app/' . $cleanRaw),
                public_path('storage/' . $cleanRaw),
                public_path($cleanRaw),
            ];

            foreach ($possiblePaths as $p) {
                if (file_exists($p) && is_file($p) && filesize($p) > 0) {
                    $filePath = $p;
                    break;
                }
            }
        }

        if (!$filePath || !file_exists($filePath) || filesize($filePath) === 0) {
            abort(404, 'فایل فیزیکی روی سرور یافت نشد یا حجم آن صفر بایت است.');
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        // فایل همان‌طور که ذخیره شده (از قبل ممهور) نمایش داده می‌شود.
        $headers = [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . rawurlencode($originalName) . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
            'Content-Length'      => (string) filesize($filePath),
        ];

        return response()->file($filePath, $headers);
    }

    public function showByToken($token)
    {
        $archive = Archive::with(['patient', 'doctor', 'items'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'archive' => $archive
        ]);
    }

    public function portalVerify(Request $request, $token)
    {
        $request->validate([
            'national_code' => 'required|string',
        ]);

        $archive = Archive::where('tracking_token', $token)->first();

        if (!$archive) {
            return response()->json([
                'status'  => 'error',
                'message' => 'پرونده‌ای با این مشخصات یافت نشد یا لینک منقضی شده است.'
            ], 404);
        }

        $inputNationalCode   = trim($request->input('national_code'));
        $archiveNationalCode = trim($archive->national_code);

        if ($inputNationalCode !== $archiveNationalCode) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کد ملی وارد شده با مشخصات این پرونده مطابقت ندارد.'
            ], 422);
        }

        return response()->json([
            'status'       => 'success',
            'message'      => 'احراز هویت با موفقیت انجام شد.',
            'patient_name' => $archive->patient_name
        ]);
    }

    /**
     * ساخت PDF فاکتور پرتال بیمار با mPDF
     * برای دیباگ HTML در مرورگر: افزودن ?html=1 به انتهای آدرس
     */
    public function portalInvoicePdf(Request $request, $token)
    {
        // جستجوی هوشمند بر اساس tracking_token، id یا file_number
        $archive = Archive::query()
            ->where(function ($q) use ($token) {
                $q->where('tracking_token', $token);
                if (ctype_digit((string) $token)) {
                    $q->orWhere('id', (int) $token);
                }
                $q->orWhere('file_number', (string) $token);
            })
            ->firstOrFail();

        $data = is_array($archive->form_data)
            ? $archive->form_data
            : (json_decode($archive->form_data, true) ?: []);
        $invoiceDetails = $data['invoiceDetails'] ?? [];

        // ۱. اطلاعات بیمار
        // اطلاعات ثبت‌شده در پنل بیماران (بر اساس کد ملی پرونده)
        $patientRec = null;
        try {
            $patientRec = \App\Models\Patient::where('national_code', trim((string) $archive->national_code))->first();
        } catch (\Throwable $e) {
            $patientRec = null;
        }
        $pa = $patientRec ? $patientRec->getAttributes() : [];

        $panelName = trim(($pa['first_name'] ?? '') . ' ' . ($pa['last_name'] ?? ''));
        if ($panelName === '') {
            $panelName = $pa['name'] ?? $pa['full_name'] ?? '';
        }

        $patient = [
            'name'          => $panelName !== '' ? $panelName : ($archive->patient_name ?? 'نامشخص'),
            'national_code' => $pa['national_code'] ?? $archive->national_code ?? '---',
            'phone'         => $pa['mobile'] ?? $pa['phone'] ?? $archive->mobile ?? '---',
        ];

        $g = strtolower(trim((string) ($pa['gender'] ?? $pa['sex'] ?? '')));
        $genderTitle = in_array($g, ['male', 'm', 'man', 'مرد', '1'], true) ? 'آقای'
            : (in_array($g, ['female', 'f', 'woman', 'زن', '2'], true) ? 'خانم' : 'آقا/خانم');

        // ۲. ردیف خدمات (زیرخدمت‌ها به‌عنوان اقلام اصلی جدول)
        $serviceList = $data['services'] ?? [];
        $serviceMeta = [];
        foreach ($serviceList as $s) {
            $serviceMeta[(string) ($s['serviceId'] ?? '')] = $s;
        }

        $pick = function (array $arr, array $keys) {
            foreach ($keys as $k) {
                if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) {
                    return $arr[$k];
                }
            }
            return null;
        };

        $findService = function ($sid) {
            $sid = (string) $sid;
            if ($sid === '' || $sid === 'other') {
                return null;
            }
            $rec = ctype_digit($sid) ? \App\Models\Service::find((int) $sid) : null;
            return $rec ?: \App\Models\Service::where('code', $sid)->first();
        };

        $expanded = false;
        $rows = [];
        foreach (($invoiceDetails['items'] ?? []) as $item) {
            $sid        = (string) ($item['serviceId'] ?? '');
            $meta       = $serviceMeta[$sid] ?? [];
            $qty        = max(1, (int) ($item['count'] ?? 1));
            $doctorName = $meta['doctorName'] ?? $item['doctorName'] ?? null;

            $parentRec  = $findService($sid);
            $parentName = $item['serviceTitle'] ?? $meta['serviceTitle'] ?? $parentRec?->name ?? 'خدمت درمانی';

            // زیرخدمت‌های ذخیره‌شده در آیتم (اگر فرانت ذخیره کرده باشد)
            $subs = $pick($item, ['children', 'subItems', 'sub_items', 'subservices', 'subServices']);
            $subs = is_array($subs) ? $subs : [];

            if (empty($subs)) {
                $childId    = $pick($item, ['subserviceId', 'subServiceId', 'subservice_id', 'childId', 'child_id', 'itemId']);
                $childTitle = $pick($item, ['subserviceTitle', 'subserviceName', 'subservice_name', 'childTitle', 'itemTitle', 'item_title']);
                if ($childId !== null || $childTitle !== null) {
                    $subs = [[
                        'id'    => $childId,
                        'title' => $childTitle,
                        'price' => $item['price'] ?? null,
                        'count' => $qty,
                        'total' => $item['total'] ?? null,
                    ]];
                }
            }

            // اگر خدمت انتخاب‌شده والد است و زیرخدمت ذخیره نشده، اقلام زیرمجموعه‌اش درج شود
            if (empty($subs) && $parentRec && !$parentRec->is_visit) {
                foreach ($parentRec->children as $child) {
                    $subs[] = [
                        'id'    => $child->id,
                        'title' => $child->name,
                        'price' => $child->price,
                        'count' => 1,
                        'code'  => $child->code,
                    ];
                }
                if (!empty($subs)) {
                    $expanded = true;
                }
            }

            if (!empty($subs)) {
                foreach ($subs as $sub) {
                    $subId  = $pick($sub, ['id', 'serviceId', 'subserviceId']);
                    $subRec = ($subId !== null && ctype_digit((string) $subId)) ? \App\Models\Service::find((int) $subId) : null;
                    $subQty = max(1, (int) ($sub['count'] ?? 1));
                    $price  = (float) ($sub['price'] ?? $subRec?->price ?? 0);
                    $total  = isset($sub['total']) ? (float) $sub['total'] : $price * $subQty;
                    $code   = $sub['code'] ?? $subRec?->code ?? '-';

                    $rows[] = [
                        'code'         => $code ?: '-',
                        'parent_name'  => $parentName,
                        'service_name' => $pick($sub, ['title', 'name', 'serviceTitle']) ?? $subRec?->name ?? $parentName,
                        'amount'       => $total,
                        'doctor_name'  => $doctorName,
                    ];
                }
                continue;
            }

            $code = $meta['serviceCode'] ?? $meta['service_code'] ?? '-';
            if (($code === '-' || $code === '' || $code === null) && $parentRec) {
                $code = $parentRec->code ?: '-';
            }

            $rows[] = [
                'code'         => $code ?: '-',
                'parent_name'  => null,
                'service_name' => $parentName,
                'amount'       => (float) ($item['total'] ?? (($item['price'] ?? 0) * $qty)),
                'doctor_name'  => $doctorName,
            ];
        }

        if (empty($rows)) {
            foreach ($serviceList as $s) {
                $rows[] = [
                    'code'         => $s['serviceCode'] ?? $s['service_code'] ?? '-',
                    'parent_name'  => null,
                    'service_name' => $s['serviceTitle'] ?? 'خدمت درمانی',
                    'amount'       => 0.0,
                    'doctor_name'  => $s['doctorName'] ?? null,
                ];
            }
        }

        // ۳. پزشک خدمت «ویزیت» (خدمتی که تیک is_visit دارد) + مهرش
        $visitDoctorIds = [];
        foreach ($serviceList as $s) {
            $srec = $findService($s['serviceId'] ?? '');
            $isVisit = $srec
                ? (bool) $srec->is_visit
                : mb_strpos((string) ($s['serviceTitle'] ?? ''), 'ویزیت') !== false;
            if ($isVisit && !empty($s['doctorId'])) {
                $visitDoctorIds[] = $s['doctorId'];
            }
        }
        $visitDoctorIds = array_values(array_unique($visitDoctorIds));

        $visitDoctors = [];
        foreach ($visitDoctorIds as $did) {
            $rec   = Doctor::find($did);
            $attrs = $rec ? $rec->getAttributes() : [];
            $fallbackName = collect($serviceList)->firstWhere('doctorId', $did)['doctorName'] ?? null;

            // هر ستونی که اسمش شبیه مهر/امضا باشد
            $stampPath = null;
            foreach ($attrs as $k => $v) {
                if ($v && is_string($v) && preg_match('/stamp|signature|seal/i', (string) $k)) {
                    $stampPath = $v;
                    break;
                }
            }
            $stamp = $this->resolveImageUrl($stampPath);

            // اگر در دیتابیس نبود: جستجو در پوشه doctors با شناسه پزشک
            if (!$stamp) {
                foreach (glob(storage_path("app/public/doctors/*{$did}*")) ?: [] as $f) {
                    if (is_file($f)) {
                        $stamp = $this->resolveImageUrl('doctors/' . basename($f));
                        if ($stamp) {
                            break;
                        }
                    }
                }
            }

            $visitDoctors[] = [
                'name'  => $attrs['name'] ?? $fallbackName ?? '---',
                'stamp' => $stamp,
            ];
        }

        // ۴. متن تکمیلی گواهی (اختیاری)
        $doctorPrescriptionText = $data['doctorPrescriptionText']
            ?? $data['prescription']
            ?? $data['doctor_note']
            ?? $invoiceDetails['doctorPrescriptionText']
            ?? $invoiceDetails['prescription']
            ?? $invoiceDetails['doctor_note']
            ?? '';

        if (is_array($doctorPrescriptionText)) {
            $doctorPrescriptionText = implode("\n", array_filter($doctorPrescriptionText));
        }

        // ۵. محاسبات (تومان)
        $totalAmount = array_sum(array_column($rows, 'amount'));
        $calcTotal   = (float) ($invoiceDetails['totalAmount'] ?? $totalAmount);
        if ($expanded || ($calcTotal <= 0 && $totalAmount > 0)) {
            $calcTotal = (float) $totalAmount;
        }
        $discount  = (float) ($invoiceDetails['discount'] ?? 0);
        $insurance = (float) ($invoiceDetails['insurance_amount'] ?? 0);
        $payable   = max(0, $calcTotal - $discount - $insurance);

        $paymentLabels = ['cash' => 'نقدی', 'card' => 'کارتخوان', 'pos' => 'کارتخوان', 'online' => 'آنلاین', 'transfer' => 'کارت به کارت'];
        $pm = $invoiceDetails['paymentMethod'] ?? null;

        $invoice = [
            'file_number'     => $archive->file_number ?: str_pad((string) $archive->id, 8, '0', STR_PAD_LEFT),
            'invoice_number'  => $invoiceDetails['invoiceNumber'] ?? ('INV-' . $archive->id),
            'tracking_code'   => Str::limit($token, 12, '...'),
            'issued_at'       => $this->toPersianDate($invoiceDetails['created_at'] ?? $archive->issued_at ?? $archive->created_at ?? now()),
            'total_amount'    => $calcTotal,
            'discount'        => $discount,
            'insurance_share' => $insurance,
            'payable_amount'  => $payable,
            'payment_method'  => $pm ? ($paymentLabels[$pm] ?? $pm) : null,
        ];

        // یافتن لوگو (با بررسی نام‌های مختلف)
        $logo = $this->resolveImageUrl('images/logo.png')
            ?? $this->resolveImageUrl('images/logo.jpg')
            ?? $this->resolveImageUrl('images/logo.jpeg')
            ?? $this->resolveImageUrl('logo.png')
            ?? $this->resolveImageUrl('logo.jpg');

        $certServices = collect($serviceList)->pluck('serviceTitle')->filter()->unique()->implode('، ');
        if ($certServices === '') {
            $certServices = collect($rows)->pluck('service_name')->filter()->unique()->implode('، ');
        }

        $cert = [
            'title'         => $genderTitle,
            'name'          => $patient['name'],
            'national_code' => $patient['national_code'],
            'date'          => substr($this->toPersianDate($data['submittedAt'] ?? $archive->issued_at ?? $archive->created_at ?? now()), 0, 10),
            'services'      => $certServices,
        ];

        $viewData = compact('rows', 'invoice', 'patient', 'visitDoctors', 'doctorPrescriptionText', 'logo', 'cert');

        // حالت دیباگ: نمایش HTML در مرورگر
        if ($request->boolean('html')) {
            return view('pdf.portal-invoice', $viewData);
        }

        // ۶. تنظیم محدودیت‌های حافظه و پردازش عبارات منظم قبل از پردازش PDF
        ini_set('pcre.backtrack_limit', '50000000');
        ini_set('pcre.recursion_limit', '20000000');
        ini_set('memory_limit', '512M');

        // ۷. ساخت PDF با mPDF
        $html = view('pdf.portal-invoice', $viewData)->render();

        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode'                 => 'utf-8',
            'format'               => 'A4',
            'default_font'         => 'dejavusans',
            'margin_top'           => 10,
            'margin_bottom'        => 32,
            'margin_footer'        => 8,
            'margin_left'          => 12,
            'margin_right'         => 12,
            'tempDir'              => $tempDir,
            'autoScriptToLang'     => true,
            'autoLangToFont'       => true,
            'shrink_tables_to_fit' => 0,
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('invoice.pdf', Destination::STRING_RETURN);

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $archive->id . '.pdf"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }


    private function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if ($parsedPath) {
                $path = $parsedPath;
            }
        }

        $cleanPath = ltrim(str_replace(['/storage/', 'storage/', 'public/'], '', $path), '/\\');

        // جلوگیری از پیمایش مسیر (Path Traversal) مثل ../../.env
        if ($cleanPath === '' || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $cleanPath)) {
            return null;
        }

        $candidates = array_unique([
            storage_path('app/public/' . $cleanPath),
            storage_path('app/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            public_path($cleanPath),
            base_path('public/' . $cleanPath),
            storage_path('app/public/images/' . basename($cleanPath)),
            public_path('images/' . basename($cleanPath)),
            storage_path('app/public/stamps/' . basename($cleanPath)),
            storage_path('app/stamps/' . basename($cleanPath)),
            public_path('storage/stamps/' . basename($cleanPath)),
            public_path('stamps/' . basename($cleanPath)),
        ]);

        Log::debug('image candidates', array_map(fn($p) => ['path' => $p, 'exists' => file_exists($p)], $candidates));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate) && is_file($candidate) && filesize($candidate) > 0) {
                $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png'  => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'gif'  => 'image/gif',
                    default => @mime_content_type($candidate) ?: 'image/png',
                };

                $data = base64_encode(file_get_contents($candidate));
                return "data:{$mime};base64,{$data}";
            }
        }

        return null;
    }


    private function toPersianDate($date): string
    {
        $carbon = Carbon::parse($date)->setTimezone('Asia/Tehran');

        if (class_exists(\Morilog\Jalali\Jalalian::class)) {
            return \Morilog\Jalali\Jalalian::fromCarbon($carbon)->format('Y/m/d H:i:s');
        }

        $time = $carbon->format('H:i:s');
        $g_y = (int)$carbon->format('Y');
        $g_m = (int)$carbon->format('n');
        $g_d = (int)$carbon->format('j');

        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;

        $g_day_no = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ($g_m > 2 && (($g_y % 4 == 0 && $g_y % 100 != 0) || ($g_y % 400 == 0))) {
            $g_days_in_month[1] = 29;
        }
        for ($i = 0; $i < $gm; ++$i) {
            $g_day_no += $g_days_in_month[$i];
        }
        $g_day_no += $gd;

        $j_day_no = $g_day_no - 79;
        $j_np = intdiv($j_day_no, 12053);
        $j_day_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * intdiv($j_day_no, 1461);
        $j_day_no %= 1461;

        if ($j_day_no >= 366) {
            $jy += intdiv($j_day_no - 1, 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }

        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
            $j_day_no -= $j_days_in_month[$i];
        }
        $jm = $i + 1;
        $jd = $j_day_no + 1;

        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
    }

    public function getAttachment($id, $index)
    {
        $archive = Archive::findOrFail($id);

        $attachments = is_array($archive->attachments)
            ? $archive->attachments
            : (json_decode($archive->attachments, true) ?? []);

        if (empty($attachments)) {
            $formData = is_array($archive->form_data)
                ? $archive->form_data
                : (json_decode($archive->form_data, true) ?? []);
            $attachments = $formData['attachments'] ?? $formData['files'] ?? [];
        }

        if (!isset($attachments[$index])) {
            return response()->json(['message' => 'پیوست مورد نظر یافت نشد.'], 404);
        }

        $fileItem = $attachments[$index];
        $filePath = is_array($fileItem)
            ? ($fileItem['path'] ?? $fileItem['url'] ?? null)
            : $fileItem;

        if (!$filePath) {
            return response()->json(['message' => 'مسیر فایل نامعتبر است.'], 404);
        }

        $cleanPath = ltrim(str_replace(['/storage/', 'storage/', 'public/'], '', $filePath), '/\\');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->download($cleanPath);
        }

        if (Storage::disk('local')->exists($cleanPath)) {
            return Storage::disk('local')->download($cleanPath);
        }

        if (file_exists(public_path($cleanPath))) {
            return response()->download(public_path($cleanPath));
        }

        Log::error("Archive attachment physical file not found at path: {$cleanPath} for archive ID: {$id}");

        return response()->json(['message' => 'فایل فیزیکی روی سرور یافت نشد.'], 404);
    }

    private function addToHistory(Archive $archive, string $action, string $details = '', $customUser = null): void
    {
        $history = is_array($archive->history)
            ? $archive->history
            : (json_decode($archive->history, true) ?: []);

        $user = $customUser ?: (Auth::user() ?? request()->user());

        $userName = is_string($user)
            ? $user
            : ($user ? ($user->name ?? $user->username ?? 'کاربر پذیرش') : ($archive->issued_by_name ?? 'کاربر پذیرش'));

        $history[] = [
            'id'                 => count($history) + 1,
            'user'               => [
                'name' => $userName
            ],
            'user_name'          => $userName,
            'action'             => $action,
            'action_description' => $details ?: $action,
            'created_at'         => now()->toIso8601String(),
        ];

        $archive->update(['history' => $history]);
    }

    /**
     * پیدا کردن خدماتِ «پیوست اجباری» که برای آن‌ها هیچ فایلی ارسال نشده است.
     * - خدمات ثبت‌شده از form_data['services'] خوانده می‌شوند.
     * - شناسه خدمتِ هر فایل از file_services[] (هم‌اندیس با files[]) خوانده می‌شود.
     *
     * @return array<int,string> نام خدمات بدون فایل (کلید = شناسه خدمت)
     */
    private function findMissingRequiredAttachments(Request $request, $formData): array
    {
        $services = is_array($formData) ? ($formData['services'] ?? []) : [];
        if (!is_array($services) || empty($services)) {
            return [];
        }

        $resolveService = function ($sid) {
            $sid = trim((string) $sid);
            if ($sid === '' || $sid === 'other') {
                return null;
            }
            $rec = ctype_digit($sid) ? Service::find((int) $sid) : null;
            return $rec ?: Service::where('code', $sid)->first();
        };

        // شناسه خدماتی که حداقل یک فایل برایشان ارسال شده است
        $serviceIdsWithFile = [];
        $uploaded = $request->file('files', []);
        $fileServices = $request->input('file_services', []);

        foreach ($uploaded as $n => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            $rec = $resolveService($fileServices[$n] ?? '');
            if ($rec) {
                $serviceIdsWithFile[$rec->id] = true;
            }
        }

        $missing = [];
        foreach ($services as $s) {
            if (!is_array($s)) {
                continue;
            }

            $rec = !empty($s['serviceDbId'])
                ? Service::find((int) $s['serviceDbId'])
                : $resolveService($s['serviceId'] ?? '');

            if ($rec && $rec->requires_attachment && !isset($serviceIdsWithFile[$rec->id])) {
                $missing[$rec->id] = $rec->name;
            }
        }

        return $missing;
    }
}
