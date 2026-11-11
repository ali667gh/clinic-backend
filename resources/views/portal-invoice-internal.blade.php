<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>چاپ داخلی – مرکز آریتمی تهران</title>
    <style>
        @page {
            size: A5;
            margin: 7mm 8mm 16mm 8mm;
            margin-footer: 4mm;
            odd-footer-name: internalFooter;
            even-footer-name: internalFooter;
        }

        body {
            font-family: 'dejavusans', 'vazir', 'tahoma', sans-serif;
            font-size: 9.5px;
            color: #1a202c;
            direction: rtl;
            text-align: right;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .head-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #cbd5e0;
        }

        .head-table td {
            padding: 3px 5px 5px 5px;
            vertical-align: middle;
        }

        .clinic-title {
            font-size: 15px;
            font-weight: bold;
            color: #1a365d;
        }

        .clinic-sub {
            font-size: 9px;
            color: #4a5568;
            margin-top: 1px;
        }

        .meta {
            font-size: 8.5px;
            color: #2d3748;
            text-align: left;
            line-height: 1.6;
        }

        .patient-bar {
            width: 100%;
            border-collapse: collapse;
            border: 1.2px solid #cbd5e0;
            background: #f7fafc;
            margin: 4px 0 6px 0;
        }

        .patient-bar td {
            padding: 4px 7px;
            font-size: 9.5px;
        }

        .cert-box {
            border: 1.2px dashed #94a3b8;
            background: #f8fafc;
            padding: 7px 10px;
            margin-bottom: 6px;
            border-radius: 4px;
        }

        .cert-text {
            font-size: 9.5px;
            color: #2d3748;
            line-height: 1.75;
            text-align: justify;
        }

        .section-title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #1a365d;
            margin: 4px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
        }

        .items-table th {
            background: #edf2f7;
            color: #2d3748;
            border: 1.2px solid #cbd5e0;
            padding: 4px 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-align: center;
        }

        .items-table td {
            border: 1.2px solid #cbd5e0;
            padding: 3px;
            font-size: 8.5px;
            text-align: center;
        }

        .sum-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sum-table td {
            border: 1.2px solid #cbd5e0;
            padding: 4px 7px;
            font-size: 9.5px;
        }

        .stamp-box {
            height: 48px;
            border: 1px dashed #94a3b8;
            border-radius: 4px;
            margin-top: 4px;
        }

        .doc-name {
            font-weight: bold;
            font-size: 9px;
            color: #1a202c;
            margin-top: 2px;
            text-align: center;
        }

        .footer-table {
            width: 100%;
            border-top: 2px solid #0f766e;
            border-collapse: collapse;
        }

        .footer-table td {
            padding-top: 3px;
            font-size: 8px;
            color: #2d3748;
            vertical-align: top;
        }
    </style>
</head>

<body>

    @php
    $visitDoctors = $visitDoctors ?? [];
    $hasPrescription = trim((string)($doctorPrescriptionText ?? '')) !== '';
    @endphp

    <htmlpagefooter name="internalFooter">
        <table class="footer-table" dir="ltr">
            <tr>
                <td style="width:35%;text-align:left;direction:ltr;">
                    <strong style="color:#0f766e;">www.TehranEP.center</strong><br>
                    Tel: +98(21) 88887270
                </td>
                <td style="width:65%;text-align:right;direction:rtl;">
                    <strong>تهران – خيابان وليعصر – خيابان توانير – نبش روانسر</strong><br>
                    <span>تلفن: </span><span style="direction:ltr;display:inline-block;">{{ fa_num('021_88887270') }}</span>
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    <sethtmlpagefooter name="internalFooter" value="on" />

    {{-- هدر --}}
    <table class="head-table">
        <tr>
            <td style="width:15%;text-align:left;padding-left:5px;">
                @if(!empty($logo))
                <img src="{{ $logo }}" alt="لوگو" style="height:40px;width:auto;">
                @endif
            </td>
            <td style="width:55%;text-align:right;">
                <div class="clinic-title">مرکز آریتمی تهران</div>
                <div class="clinic-sub">تشخيص و درمان اختلالات ضربان قلب</div>
            </td>
            <td class="meta" style="width:30%;">
                <div><strong>شماره پرونده:</strong> #{{ fa_num($invoice['file_number'] ?? '---') }}</div>
                <div><strong>شماره فاکتور:</strong> {{ fa_num($invoice['invoice_number'] ?? '---') }}</div>
                <div><strong>کد رهگیری:</strong> {{ fa_num($invoice['tracking_code'] ?? '---') }}</div>
                <div><strong>تاریخ صدور:</strong> {{ fa_num($invoice['issued_at'] ?? '---') }}</div>
            </td>
        </tr>
    </table>

    {{-- اطلاعات بیمار --}}
    <table class="patient-bar">
        <tr>
            <td><strong>بیمار:</strong> {{ $patient['name'] ?? '---' }}</td>
            <td><strong>کد ملی:</strong> {{ fa_num($patient['national_code'] ?? '---') }}</td>
            <td><strong>موبایل:</strong> {{ fa_num($patient['phone'] ?? '---') }}</td>
        </tr>
    </table>

    {{-- تاییدیه پزشک --}}
    <div class="cert-box">
        <div class="cert-text">
            {{ $cert['title'] ?? '' }} <strong>{{ $cert['name'] ?? '' }}</strong>
            با کد ملی <strong>{{ fa_num($cert['national_code'] ?? '---') }}</strong>
            در تاریخ <strong>{{ fa_num($cert['date'] ?? '---') }}</strong>
            به مرکز آریتمی تهران مراجعه نموده و خدمات
            <strong>{{ $cert['services'] ?? '' }}</strong>
            برای ایشان ثبت و انجام شود
            @if($hasPrescription)
            <br>{!! nl2br(e($doctorPrescriptionText)) !!}
            @endif
        </div>
    </div>

    {{-- ریز اقلام --}}
    <div class="section-title">صورتحساب درمانی</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:13%;">کد خدمت</th>
                <th style="width:43%;">شرح خدمت / آزمایش</th>
                <th style="width:21%;">پزشک معالج</th>
                <th style="width:18%;">مبلغ (تومان)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
            <tr>
                <td>{{ fa_num($index + 1) }}</td>
                <td>{{ fa_num($row['code'] ?? '---') }}</td>
                <td style="text-align:right;">{{ $row['service_name'] ?? '' }}</td>
                <td>{{ $row['doctor_name'] ?? '---' }}</td>
                <td>{{ fa_num($row['amount'] ?? 0, true) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding:8px;color:#a0aec0;">هیچ ردیف خدمتی ثبت نشده است.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- جمع‌بندی + جای خالی امضا کنار هم --}}
    <table style="width:100%;margin-top:7px;border-collapse:collapse;">
        <tr>
            {{-- جای خالی مهر و امضا – بدون تصویر --}}
            <td style="width:50%;vertical-align:top;padding-left:6px;">
                <div style="font-weight:bold;font-size:9.5px;color:#2d3748;
                        border-bottom:1px solid #cbd5e0;padding-bottom:3px;margin-bottom:4px;">
                    مهر و امضای پزشک:
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        @forelse($visitDoctors as $doc)
                        <td style="text-align:center;padding:2px;">
                            <div class="stamp-box"></div>
                            <div class="doc-name">{{ $doc['name'] ?? '' }}</div>
                        </td>
                        @empty
                        <td>
                            <div class="stamp-box"></div>
                        </td>
                        @endforelse
                    </tr>
                </table>
            </td>
            {{-- مبالغ --}}
            <td style="width:50%;vertical-align:top;">
                <table class="sum-table">
                    <tr>
                        <td style="color:#4a5568;">مجموع خدمات:</td>
                        <td style="text-align:left;">
                            <strong>{{ fa_num($invoice['total_amount'] ?? 0, true) }}</strong> تومان
                        </td>
                    </tr>
                    @if(($invoice['discount'] ?? 0) > 0)
                    <tr>
                        <td style="color:#e53e3e;">تخفیف:</td>
                        <td style="text-align:left;color:#e53e3e;">
                            {{ fa_num($invoice['discount'], true) }} تومان
                        </td>
                    </tr>
                    @endif
                    @if(($invoice['insurance_share'] ?? 0) > 0)
                    <tr>
                        <td style="color:#2b6cb0;">سهم بیمه:</td>
                        <td style="text-align:left;color:#2b6cb0;">
                            {{ fa_num($invoice['insurance_share'], true) }} تومان
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="color:#4a5568;">مبلغ نهایی پرداختی:</td>
                        <td style="text-align:left;color:#15803d;">
                            <strong>{{ fa_num($invoice['payable_amount'] ?? 0, true) }}</strong> تومان
                        </td>
                    </tr>
                    @if(!empty($invoice['payment_method']))
                    <tr>
                        <td style="color:#4a5568;">روش پرداخت:</td>
                        <td style="text-align:left;">{{ $invoice['payment_method'] }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

</body>

</html>