<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>صورتحساب رسمی مرکز آریتمی تهران</title>

    <style>
        @page {
            margin-top: 12mm;
            margin-bottom: 22mm;
            margin-left: 10mm;
            margin-right: 10mm;
            margin-footer: 5mm;
            odd-footer-name: clinicFooter;
            even-footer-name: clinicFooter;
        }

        body {
            font-family: 'dejavusans', 'vazir', 'tahoma', sans-serif;
            font-size: 10.5px;
            color: #1a202c;
            direction: rtl;
            text-align: right;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .page-banner {
            background-color: #e8edf3;
            color: #2d3748;
            font-size: 9.5px;
            font-weight: bold;
            padding: 4px 8px;
            margin-bottom: 8px;
        }

        .head-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #cbd5e0;
        }

        .head-table td {
            padding: 2px 4px 6px 4px;
            vertical-align: middle;
        }

        .head-table td img {
            display: block;
        }

        .clinic-title {
            font-size: 18px;
            font-weight: bold;
            color: #1a365d;
        }

        .clinic-sub {
            font-size: 10px;
            color: #4a5568;
        }

        .meta {
            font-size: 9px;
            color: #2d3748;
            text-align: left;
            line-height: 1.6;
        }

        .patient-bar {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e0;
            background-color: #f7fafc;
            margin-bottom: 8px;
        }

        .patient-bar td {
            padding: 5px 8px;
            font-size: 10px;
        }

        .invoice-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            color: #1a365d;
            margin: 4px 0 8px 0;
            padding: 2px 0;
        }

        .cert-box {
            border: 1px dashed #a0aec0;
            background-color: #f8fafc;
            padding: 10px 12px;
            margin-top: 4px;
        }

        .cert-text {
            font-size: 10.5px;
            color: #2d3748;
            line-height: 1.9;
            text-align: justify;
        }

        .doctors-title {
            font-weight: bold;
            font-size: 11px;
            color: #2d3748;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 4px;
            margin-top: 18px;
        }

        .doctors-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .doctors-table td {
            text-align: center;
            vertical-align: bottom;
            padding: 4px;
        }

        .doc-name {
            font-weight: bold;
            font-size: 10.5px;
            color: #1a202c;
            margin-top: 3px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .items-table th {
            background-color: #edf2f7;
            color: #2d3748;
            border: 1px solid #cbd5e0;
            padding: 6px 4px;
            font-size: 9.5px;
            font-weight: bold;
            text-align: center;
        }

        .items-table td {
            border: 1px solid #cbd5e0;
            padding: 5px 4px;
            font-size: 9.5px;
            text-align: center;
        }

        .sum-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sum-table td {
            border: 1px solid #cbd5e0;
            padding: 6px 8px;
            font-size: 10px;
        }

        .footer-table {
            width: 100%;
            border-top: 2.5px solid #0f766e;
            border-collapse: collapse;
        }

        .footer-table td {
            padding-top: 4px;
            font-size: 9px;
            color: #2d3748;
            vertical-align: top;
        }
    </style>
</head>

<body>

    @php
    $visitDoctors = $visitDoctors ?? [];
    $hasPrescription = trim((string) ($doctorPrescriptionText ?? '')) !== '';
    @endphp

    {{-- تعریف فوتر --}}
    <htmlpagefooter name="clinicFooter">
        <table class="footer-table" dir="ltr">
            <tr>
                <td style="width: 38%; text-align: left; direction: ltr;">
                    <strong style="color: #0f766e; font-size: 11px;">www.TehranEP.center</strong><br>
                    Tel &amp; WhatsApp: +98(21) 88887270
                </td>
                <td style="width: 62%; text-align: right; direction: rtl;">
                    <strong>تهران – خيابان وليعصر – خيابان توانير – نرسيده به ميدان – نبش روانسر</strong><br>
                    تلفن: {{ fa_num('021 88887270') }}
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    {{-- فعال‌سازی برای صفحه اول --}}
    <sethtmlpagefooter name="clinicFooter" value="on" />

    {{-- ===================== صفحه ۱ ===================== --}}
    <table class="head-table">
        <tr>
            <td style="width: 16%; text-align: left; padding-left: 8px;">
                @if(!empty($logo))
                <img src="{{ $logo }}" alt="لوگو" style="height: 52px; width: auto;">
                @endif
            </td>
            <td style="width: 54%; text-align: right;">
                <div class="clinic-title">مرکز آریتمی تهران</div>
                <div class="clinic-sub">تشخ يص و درمان اختلالات ضربان قلب</div>
            </td>
            <td class="meta" style="width: 30%;">
                <div><strong>شماره پرونده:</strong> #{{ fa_num($invoice['file_number'] ?? '---') }}</div>
                <div><strong>شماره فاکتور:</strong> {{ fa_num($invoice['invoice_number'] ?? '---') }}</div>
                <div><strong>کد رهگیری:</strong> {{ fa_num($invoice['tracking_code'] ?? '---') }}</div>
                <div><strong>تاریخ صدور:</strong> {{ fa_num($invoice['issued_at'] ?? '---') }}</div>
            </td>
        </tr>
    </table>

    <table class="patient-bar">
        <tr>
            <td><strong>بیمار:</strong> {{ $patient['name'] ?? '---' }}</td>
            <td><strong>کد ملی:</strong> {{ fa_num($patient['national_code'] ?? '---') }}</td>
            <td><strong>شماره موبایل:</strong> {{ fa_num($patient['phone'] ?? '---') }}</td>
        </tr>
    </table>

    <div class="page-banner">صفحه ۱ از ۲ (اطلاعات پرونده و تاییدیه پزشک)</div>

    <div class="cert-box">
        <div class="cert-text">
            {{ $cert['title'] ?? '' }} <strong>{{ $cert['name'] ?? '' }}</strong>
            با کد ملی <strong>{{ fa_num($cert['national_code'] ?? '---') }}</strong>
            در تاریخ <strong>{{ fa_num($cert['date'] ?? '---') }}</strong>
            به مرکز آریتمی تهران مراجعه نموده و خدمات
            <strong>{{ $cert['services'] ?? '' }}</strong>
            برای ایشان ثبت و انجام شده است.
            @if($hasPrescription)
            <br>{!! nl2br(e($doctorPrescriptionText)) !!}
            @endif
        </div>
    </div>

    <div class="doctors-title">مهر و امضای پزشک:</div>

    <table class="doctors-table">
        <tr>
            @forelse($visitDoctors as $doc)
            <td>
                @if(!empty($doc['stamp']))
                <img src="{{ $doc['stamp'] }}" alt="مهر پزشک" style="max-height: 105px; max-width: 200px;">
                @else
                <div style="height: 75px;"></div>
                @endif
                <div class="doc-name">{{ $doc['name'] ?? '' }}</div>
            </td>
            @empty
            <td>
                <div style="height: 75px;"></div>
            </td>
            @endforelse
        </tr>
    </table>

    <pagebreak />
    {{-- تاکید به mPDF برای اعمال مجدد فوتر در انتهای صفحه دوم --}}
    <sethtmlpagefooter name="clinicFooter" value="on" />

    {{-- ===================== صفحه ۲ ===================== --}}
    <table class="head-table">
        <tr>
            <td style="width: 16%; text-align: left; padding-left: 8px;">
                @if(!empty($logo))
                <img src="{{ $logo }}" alt="لوگو" style="height: 52px; width: auto;">
                @endif
            </td>
            <td style="width: 54%; text-align: right;">
                <div class="clinic-title">مرکز آریتمی تهران</div>
                <div class="clinic-sub">تشخ يص و درمان اختلالات ضربان قلب</div>
            </td>
            <td class="meta" style="width: 30%;">
                <div><strong>شماره پرونده:</strong> #{{ fa_num($invoice['file_number'] ?? '---') }}</div>
                <div><strong>شماره فاکتور:</strong> {{ fa_num($invoice['invoice_number'] ?? '---') }}</div>
                <div><strong>کد رهگیری:</strong> {{ fa_num($invoice['tracking_code'] ?? '---') }}</div>
                <div><strong>تاریخ صدور:</strong> {{ fa_num($invoice['issued_at'] ?? '---') }}</div>
            </td>
        </tr>
    </table>

    <table class="patient-bar">
        <tr>
            <td><strong>بیمار:</strong> {{ $patient['name'] ?? '---' }}</td>
            <td><strong>کد ملی:</strong> {{ fa_num($patient['national_code'] ?? '---') }}</td>
            <td><strong>شماره موبایل:</strong> {{ fa_num($patient['phone'] ?? '---') }}</td>
        </tr>
    </table>

    <div class="invoice-title">صورتحساب درمانی</div>

    <div class="page-banner">صفحه ۲ از ۲ (ریز اقلام خدمات و تسویه حساب)</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 14%;">کد خدمت</th>
                <th style="width: 40%;">شرح خدمت / آزمایش</th>
                <th style="width: 22%;">پزشک معالج / متخصص</th>
                <th style="width: 18%;">مبلغ (تومان)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
            <tr>
                <td>{{ fa_num($index + 1) }}</td>
                <td>{{ fa_num($row['code'] ?? '---') }}</td>
                <td style="text-align: right;">
                    {{ $row['service_name'] ?? '' }}
                </td>
                <td>{{ $row['doctor_name'] ?? '---' }}</td>
                <td>{{ fa_num($row['amount'] ?? 0, true) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding: 12px; color: #a0aec0;">هیچ ردیف خدمتی ثبت نشده است.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 12px; border-collapse: collapse;">
        <tr>
            <td style="width: 50%;"></td>
            <td style="width: 50%; vertical-align: top;">
                <table class="sum-table">
                    <tr>
                        <td style="color: #4a5568;">مجموع خدمات:</td>
                        <td style="text-align: left;"><strong>{{ fa_num($invoice['total_amount'] ?? 0, true) }}</strong> تومان</td>
                    </tr>
                    @if(($invoice['discount'] ?? 0) > 0)
                    <tr>
                        <td style="color: #e53e3e;">تخفیف:</td>
                        <td style="text-align: left; color: #e53e3e;">{{ fa_num($invoice['discount'], true) }} تومان</td>
                    </tr>
                    @endif
                    @if(($invoice['insurance_share'] ?? 0) > 0)
                    <tr>
                        <td style="color: #2b6cb0;">سهم بیمه:</td>
                        <td style="text-align: left; color: #2b6cb0;">{{ fa_num($invoice['insurance_share'], true) }} تومان</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="color: #4a5568;">مبلغ نهایی پرداختی:</td>
                        <td style="text-align: left; color: #15803d;"><strong>{{ fa_num($invoice['payable_amount'] ?? 0, true) }}</strong> تومان</td>
                    </tr>
                    @if(!empty($invoice['payment_method']))
                    <tr>
                        <td style="color: #4a5568;">روش پرداخت:</td>
                        <td style="text-align: left;">{{ $invoice['payment_method'] }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="doctors-title" style="margin-top: 18px;">مهر و امضای پزشک:</div>

    <table class="doctors-table">
        <tr>
            @forelse($visitDoctors as $doc)
            <td>
                @if(!empty($doc['stamp']))
                <img src="{{ $doc['stamp'] }}" alt="مهر پزشک" style="max-height: 95px; max-width: 180px;">
                @else
                <div style="height: 65px;"></div>
                @endif
                <div class="doc-name">{{ $doc['name'] ?? '' }}</div>
            </td>
            @empty
            <td>
                <div style="height: 65px;"></div>
            </td>
            @endforelse
        </tr>
    </table>

</body>

</html>