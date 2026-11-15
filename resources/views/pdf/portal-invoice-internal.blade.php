{{-- portal-invoice-internal.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>فاکتور داخلی – مرکز آریتمی تهران</title>
    <style>
        @page {
            margin-top: 10mm;
            margin-bottom: 20mm;
            margin-left: 8mm;
            margin-right: 8mm;
            margin-footer: 5mm;
            odd-footer-name: clinicFooter;
            even-footer-name: clinicFooter;
        }

        body {
            font-family: 'dejavusans', 'tahoma', sans-serif;
            font-size: 11px;
            color: #1a202c;
            direction: rtl;
            text-align: right;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .page-banner {
            background-color: #e8edf3;
            color: #2d3748;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 10px;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .head-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #cbd5e0;
            padding-bottom: 6px;
        }

        .head-table td {
            padding: 4px 6px 8px 6px;
            vertical-align: middle;
        }

        .clinic-title {
            font-size: 19px;
            font-weight: bold;
            color: #1a365d;
        }

        .clinic-sub {
            font-size: 11px;
            color: #4a5568;
            margin-top: 2px;
        }

        .meta {
            font-size: 10px;
            color: #2d3748;
            text-align: left;
            line-height: 1.7;
        }

        .patient-bar {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #cbd5e0;
            background-color: #f7fafc;
            margin-bottom: 8px;
            margin-top: 6px;
        }

        .patient-bar td {
            padding: 6px 10px;
            font-size: 11px;
        }

        .invoice-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #1a365d;
            margin: 6px 0 8px 0;
        }

        .cert-box {
            border: 1.5px dashed #94a3b8;
            background-color: #f8fafc;
            padding: 10px 12px;
            margin-top: 6px;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .cert-text {
            font-size: 11.5px;
            color: #2d3748;
            line-height: 1.9;
            text-align: justify;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .items-table th {
            background-color: #edf2f7;
            color: #2d3748;
            border: 1.5px solid #cbd5e0;
            padding: 6px 4px;
            font-size: 10.5px;
            font-weight: bold;
            text-align: center;
        }

        .items-table td {
            border: 1.5px solid #cbd5e0;
            padding: 5px 4px;
            font-size: 10.5px;
            text-align: center;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f7fafc;
        }

        .sum-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sum-table td {
            border: 1.5px solid #cbd5e0;
            padding: 6px 10px;
            font-size: 11px;
        }

        .prescription-box {
            border: 1.5px dashed #90cdf4;
            background-color: #ebf8ff;
            border-radius: 4px;
            padding: 8px 12px;
            margin-top: 10px;
            font-size: 11px;
            color: #2c5282;
            line-height: 1.8;
        }

        .prescription-title {
            font-weight: bold;
            color: #1a365d;
            font-size: 11.5px;
            margin-bottom: 4px;
        }

        .footer-table {
            width: 100%;
            border-top: 2px solid #0f766e;
            border-collapse: collapse;
        }

        .footer-table td {
            padding-top: 4px;
            font-size: 9.5px;
            color: #2d3748;
            vertical-align: top;
        }
    </style>
</head>

<body>

    @php
    // ── تبدیل میلادی به شمسی (بدون پکیج اضافه) ─────────────────────────
    $gregorianToJalali = function (int $gy, int $gm, int $gd): array {
    $gDaysBeforeMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
    + intdiv($gy2 + 399, 400) + $gd + $gDaysBeforeMonth[$gm - 1];

    $jy = -1595 + 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
    $jy += intdiv($days - 1, 365);
    $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm=1 + intdiv($days, 31);
        $jd=1 + ($days % 31);
        } else {
        $jm=7 + intdiv($days - 186, 30);
        $jd=1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
        };

        // تبدیل یک تاریخ ورودی (رشته یا Carbon) به فرمت شمسی Y/m/d
        $toJalaliString=function ($value) use ($gregorianToJalali): ?string {
        if ($value===null || $value==='' ) {
        return null;
        }
        try {
        $c=$value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::parse($value);
        [$jy, $jm, $jd]=$gregorianToJalali((int) $c->year, (int) $c->month, (int) $c->day);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        } catch (\Throwable $e) {
        return null;
        }
        };

        // ── تاریخ ──────────────────────────────────────────────────────────
        $displayDate = $toJalaliString($createdAt) ?? (is_string($createdAt) ? substr($createdAt, 0, 10) : '---');

        // ── اطلاعات بیمار ──────────────────────────────────────────────────
        $fileNumber = $archive->file_number ?? $archive->id ?? '---';
        $nationalCode = $patient['national_code'] ?? $archive->national_code ?? '---';
        $patientLabel = $patient['name'] ?? $patientName ?? '---';
        $patientPhone = $patient['phone'] ?? $mobile ?? '---';

        // ── گواهی ──────────────────────────────────────────────────────────
        $cert = $cert ?? [];
        $certTitle = $cert['title'] ?? 'آقا/خانم';
        $certName = $cert['name'] ?? $patientLabel;
        $certNC = $cert['national_code'] ?? $nationalCode;
        $certDate = $toJalaliString($cert['date'] ?? null) ?? $displayDate;
        $certServices = $cert['services'] ?? '';

        // ── جمع از rows ────────────────────────────────────────────────────
        $calcTotal = collect($rows)->sum('amount');
        $finalTotal = $totalAmount > 0 ? $totalAmount : $calcTotal;
        $finalPayable = $payableAmount > 0 ? $payableAmount : max($calcTotal - (float) $discount, 0);

        $hasPrescription = trim((string) ($doctorPrescriptionText ?? '')) !== '';
        @endphp

        <htmlpagefooter name="clinicFooter">
            <table class="footer-table" dir="ltr">
                <tr>
                    <td style="width: 35%; text-align: left; direction: ltr;">
                        <strong style="color: #0f766e; font-size: 11px;">www.TehranEP.center</strong><br>
                        Tel &amp; WhatsApp: +98(21) 88887270
                    </td>
                    <td style="width: 65%; text-align: right; direction: rtl; font-size: 10px;">
                        <strong>تهران – خيابان وليعصر – خيابان توانير – نرسيده به ميدان – نبش روانسر</strong><br>
                        <span>تلفن: ۰۲۱-۸۸۸۸۷۲۷۰</span>
                    </td>
                </tr>
            </table>
        </htmlpagefooter>
        <sethtmlpagefooter name="clinicFooter" value="on" />

        {{-- ── هدر کلینیک ── --}}
        <table class="head-table">
            <tr>
                <td style="width: 15%; text-align: left; padding-left: 8px;">
                    @if(!empty($logo))
                    <img src="{{ $logo }}" alt="لوگو" style="height: 52px; width: auto;">
                    @endif
                </td>
                <td style="width: 55%; text-align: right;">
                    <div class="clinic-title">مرکز آریتمی تهران</div>
                    <div class="clinic-sub">تشخيص و درمان اختلالات ضربان قلب</div>
                </td>
                <td class="meta" style="width: 30%;">
                    <div><strong>شماره پرونده:</strong>
                        @if(function_exists('fa_num')) {{ fa_num($fileNumber) }}
                        @else {{ $fileNumber }} @endif
                    </div>
                    <div><strong>شماره فاکتور:</strong>
                        @if(function_exists('fa_num')) {{ fa_num($invoiceNumber) }}
                        @else {{ $invoiceNumber }} @endif
                    </div>
                    <div><strong>تاریخ صدور:</strong>
                        @if(function_exists('fa_num')) {{ fa_num($displayDate) }}
                        @else {{ $displayDate }} @endif
                    </div>
                </td>
            </tr>
        </table>

        {{-- ── بار اطلاعات بیمار ── --}}
        <table class="patient-bar">
            <tr>
                <td style="width: 40%;"><strong>بیمار:</strong> {{ $patientLabel }}</td>
                <td style="width: 30%;"><strong>کد ملی:</strong>
                    @if(function_exists('fa_num')) {{ fa_num($nationalCode) }}
                    @else {{ $nationalCode }} @endif
                </td>
                <td style="width: 30%;"><strong>موبایل:</strong>
                    @if(function_exists('fa_num')) {{ fa_num($patientPhone) }}
                    @else {{ $patientPhone }} @endif
                </td>
            </tr>
        </table>

        <div class="page-banner">فاکتور رسمی مرکز آریتمی</div>

        {{-- ── متن گواهی ── --}}
        <div class="cert-box">
            <div class="cert-text">
                {{ $certTitle }} <strong>{{ $certName }}</strong>
                با کد ملی <strong>{{ function_exists('fa_num') ? fa_num($certNC) : $certNC }}</strong>
                در تاریخ <strong>{{ function_exists('fa_num') ? fa_num($certDate) : $certDate }}</strong>
                به مرکز آریتمی تهران مراجعه نموده و خدمات
                <strong>{{ $certServices }}</strong>
                برای ایشان ثبت و انجام شود
                @if($hasPrescription)
                <br>{!! nl2br(e($doctorPrescriptionText)) !!}
                @endif
            </div>
        </div>

        {{-- ── جدول اقلام خدمات ── --}}
        <div class="invoice-title">صورتحساب درمانی</div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th style="width: 12%;">کد خدمت</th>
                    <th style="width: 42%;">شرح خدمت / آزمایش</th>
                    <th style="width: 22%;">پزشک معالج / متخصص</th>
                    <th style="width: 18%;">مبلغ (تومان)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $index => $row)
                <tr>
                    <td>
                        @if(function_exists('fa_num')) {{ fa_num($index + 1) }}
                        @else {{ $index + 1 }} @endif
                    </td>
                    <td style="font-size: 10px;">
                        @if(function_exists('fa_num')) {{ fa_num($row['code'] ?? '---') }}
                        @else {{ $row['code'] ?? '---' }} @endif
                    </td>
                    <td style="text-align: right; padding-right: 8px;">
                        {{ $row['service_name'] ?? '' }}
                    </td>
                    <td>{{ $row['doctor_name'] ?? '---' }}</td>
                    <td>
                        @php $rowAmt = (float) ($row['amount'] ?? 0); @endphp
                        @if(function_exists('fa_num')) {{ fa_num($rowAmt, true) }}
                        @else {{ number_format($rowAmt) }} @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="padding: 14px; color: #a0aec0; text-align: center;">
                        هیچ ردیف خدمتی ثبت نشده است.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ── جدول جمع‌بندی ── --}}
        <table style="width: 100%; margin-top: 10px; border-collapse: collapse;">
            <tr>
                <td style="width: 46%;"></td>
                <td style="width: 54%; vertical-align: top;">
                    <table class="sum-table">
                        <tr>
                            <td style="color: #4a5568; width: 55%;">مجموع خدمات:</td>
                            <td style="text-align: left;">
                                <strong>
                                    @if(function_exists('fa_num')) {{ fa_num($finalTotal, true) }}
                                    @else {{ number_format($finalTotal) }} @endif
                                </strong> تومان
                            </td>
                        </tr>
                        @if((float) $discount > 0)
                        <tr>
                            <td style="color: #e53e3e;">تخفیف:</td>
                            <td style="text-align: left; color: #e53e3e;">
                                @if(function_exists('fa_num')) {{ fa_num((float) $discount, true) }}
                                @else {{ number_format((float) $discount) }} @endif
                                تومان
                            </td>
                        </tr>
                        @endif
                        <tr style="background-color: #f0fff4;">
                            <td style="color: #276749; font-weight: bold;">مبلغ قابل پرداخت:</td>
                            <td style="text-align: left; color: #15803d; font-weight: bold;">
                                @if(function_exists('fa_num')) {{ fa_num($finalPayable, true) }}
                                @else {{ number_format($finalPayable) }} @endif
                                تومان
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

</body>

</html>