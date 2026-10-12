<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Requests\PayInvoiceRequest;
use App\Models\Appointment;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * لیست فاکتورها همراه با صفحه‌بندی و روابط
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['appointment.doctor', 'appointment.service', 'patient']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $invoices = $query->latest()->paginate(15);

        return response()->json($invoices);
    }

    /**
     * ثبت فاکتور جدید
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $appointment = Appointment::findOrFail($validated['appointment_id']);

        $amount = (int) $validated['amount'];
        $discount = (int) ($validated['discount'] ?? 0);
        $finalAmount = max(0, $amount - $discount);
        $status = $validated['status'] ?? 'unpaid';

        $invoice = DB::transaction(function () use ($appointment, $validated, $amount, $discount, $finalAmount, $status) {
            return Invoice::create([
                'appointment_id' => $appointment->id,
                'patient_id'     => $appointment->patient_id,
                'amount'         => $amount,
                'discount'       => $discount,
                'final_amount'   => $finalAmount,
                'status'         => $status,
                'payment_method' => $validated['payment_method'] ?? null,
                'paid_at'        => $status === 'paid' ? now() : null,
                'notes'          => $validated['notes'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'فاکتور با موفقیت صادر شد.',
            'data'    => $invoice->load(['appointment.doctor', 'appointment.service', 'patient']),
        ], 201);
    }

    /**
     * نمایش جزئیات یک فاکتور
     */
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(
            $invoice->load(['appointment.doctor', 'appointment.service', 'patient'])
        );
    }

    /**
     * ویرایش فاکتور
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validated();

        $amount = array_key_exists('amount', $validated) ? (int) $validated['amount'] : $invoice->amount;
        $discount = array_key_exists('discount', $validated) ? (int) ($validated['discount'] ?? 0) : $invoice->discount;
        $finalAmount = max(0, $amount - $discount);

        $status = $validated['status'] ?? $invoice->status;

        $paidAt = $invoice->paid_at;
        if ($status === 'paid' && !$paidAt) {
            $paidAt = now();
        } elseif ($status !== 'paid') {
            $paidAt = null;
        }

        $invoice->update([
            ...$validated,
            'amount'       => $amount,
            'discount'     => $discount,
            'final_amount' => $finalAmount,
            'status'       => $status,
            'paid_at'      => $paidAt,
        ]);

        return response()->json([
            'message' => 'فاکتور با موفقیت بروزرسانی شد.',
            'data'    => $invoice->fresh()->load(['appointment.doctor', 'appointment.service', 'patient']),
        ]);
    }

    /**
     * حذف فاکتور
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json([
            'message' => 'فاکتور با موفقیت حذف شد.',
        ]);
    }

    /**
     * تسویه فاکتور
     */
    public function pay(PayInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'این فاکتور قبلاً پرداخت و تسویه شده است.',
            ], 422);
        }

        if ($invoice->status === 'cancelled') {
            return response()->json([
                'message' => 'فاکتور لغو شده قابل تسویه نیست.',
            ], 422);
        }

        $validated = $request->validated();

        $invoice->update([
            'status'         => 'paid',
            'payment_method' => $validated['payment_method'],
            'paid_at'        => $validated['paid_at'] ?? now(),
            'notes'          => $validated['notes'] ?? $invoice->notes,
        ]);

        return response()->json([
            'message' => 'فاکتور با موفقیت تسویه و پرداخت شد.',
            'data'    => $invoice->fresh()->load(['appointment.doctor', 'appointment.service', 'patient']),
        ]);
    }

    /**
     * خروجی PDF رسمی فاکتور (تولید با موتور استاندارد mPDF و منطق جامع ArchiveController)
     */
    public function officialPdf(Request $request, $id)
    {
        // بررسی شناسه ارسالی: فاکتور یا پرونده آرشیو
        $lookupKey = $id;

        $invoice = Invoice::with('archive')->find($id);
        if ($invoice && !empty($invoice->archive?->tracking_token)) {
            $lookupKey = $invoice->archive->tracking_token;
        } elseif ($invoice && !empty($invoice->archive_id)) {
            $lookupKey = $invoice->archive_id;
        }

        // فراخوانی مستقیم منطق کامل mPDF که در ArchiveController پیاده‌سازی شده است
        return app(ArchiveController::class)->portalInvoicePdf($request, (string) $lookupKey);
    }

    /**
     * بررسی وضعیت ویزیت یا فاکتور باز بیمار
     * بررسی هم‌زمان قفل بودن پرونده و وجود خدمت ویزیت پایه
     */
    public function visitStatus(Request $request): JsonResponse
    {
        $patientId = $request->query('patient_id');

        if (!$patientId) {
            return response()->json([
                'is_locked'           => false,
                'has_confirmed_visit' => false,
                'status'              => 'available',
                'message'             => 'شناسه بیمار ارسال نشده است.',
            ]);
        }

        // بررسی اینکه آیا بیمار فاکتور باز / تسویه نشده دارد یا خیر
        $hasPendingInvoice = Invoice::where('patient_id', $patientId)
            ->whereIn('status', ['unpaid', 'pending'])
            ->exists();

        // بررسی اینکه آیا بیمار حداقل یک فاکتور ویزیت تسویه شده دارد یا خیر
        $hasConfirmedVisit = Invoice::where('patient_id', $patientId)
            ->where('status', 'paid')
            ->whereHas('appointment.service', function ($query) {
                $query->where('is_visit', true);
            })
            ->exists();

        return response()->json([
            'is_locked'           => $hasPendingInvoice,
            'has_confirmed_visit' => $hasConfirmedVisit,
            'status'              => $hasPendingInvoice ? 'locked' : 'available',
        ]);
    }
}
