{{-- portal-invoice-internal.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>فاکتور داخلی</title>
    <style>
        @page {
            size: A5 portrait;
            margin: 10mm 10mm 15mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Vazirmatn', Vazir, Tahoma, 'Arial Unicode MS', sans-serif;
            font-size: 11px;
            color: #1a202c;
            direction: rtl;
            text-align: right;
            line-height: 1.6;
            background: #fff;
        }

        /* ── header ── */
        .head-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #cbd5e0;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .head-table td {
            padding: 3px 5px 7px 5px;
            vertical-align: middle;
        }

        .clinic-title {
            font-size: 18px;
            font-weight: bold;
            color: #1a365d;
        }

        .clinic-sub {
            font-size: 10.5px;
            color: #4a5568;
            margin-top: 2px;
        }

        .meta {
            font-size: 10px;
            color: #2d3748;
            text-align: left;
            line-height: 1.8;
        }

        /* ── patient bar ── */
        .patient-bar {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #cbd5e0;
            background-color: #f7fafc;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .patient-bar td {
            padding: 6px 10px;
            font-size: 10.5px;
        }

        /* ── banner ── */
        .page-banner {
            background-color: #e8edf3;
            color: #2d3748;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 10px;
            margin-bottom: 8px;
            border-radius: 4px;
            text-align: center;
        }

        /* ── items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .items-table th {
            background-color: #edf2f7;
            color: #2d3748;
            border: 1.5px solid #cbd5e0;
            padding: 6px 4px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
        }

        .items-table td {
            border: 1.5px solid #cbd5e0;
            padding: 5px 4px;
            font-size: 10px;
            text-align: center;
            vertical-align: middle;
        }

        .items-table td.svc {
            text-align: right;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }

        /* ── sum table ── */
        .sum-wrap {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .sum-table {
            width: 52%;
            border-collapse: collapse;
        }

        .sum-table td {
            border: 1.5px solid #cbd5e0;
            padding: 6px 10px;
            font-size: 10.5px;
        }

        .sum-table td.num {
            text-align: left;
            font-variant-numeric: tabular-nums;
        }

        .sum-table tr.payable td {
            background-color: #1a365d;
            color: #fff;
            font-weight: bold;
        }

        .sum-table tr:nth-child(even) td {
            background-color: #f7fafc;
        }

        .sum-table tr.payable td {
            background-color: #1a365d !important;
        }

        /* ── footer ── */
        .doc-footer {
            border-top: 2px solid #0f766e;
            padding-top: 5px;
            margin-top: 8px;
            font-size: 9.5px;
            color: #2d3748;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    {{-- header --}}
    <table class="head-table">
        <tr>
            <td style="width:14%; text-align:left; padding-left:6px;">
                @if(!empty($logo))
                <img src="{{ $logo }}" alt="لوگو" style="height:50px; width:auto;">
                @endif
            </td>
            <td style="width:56%; text-align:right;">
                <div class="clinic-title">مرکز آریتمی تهران</div>
                <div class="clinic-sub">تشخيص و درمان اختلالات ضربان قلب</div>
            </td>
            <td class="meta" style="width:30%;">
                <div><strong>شماره پرونده:</strong> #{{ $invoice['file_number'] ?? '---' }}</div>
                <div><strong>شماره فاکتور:</strong> {{ $invoice['invoice_number'] ?? '---' }}</div>
                <div><strong>تاریخ صدور:</strong>
                    @if(!empty($invoice['issued_at']))
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($invoice['issued_at']))->format('Y/m/d') }}
                    @else
                    ---
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- patient bar --}}
    <table class="patient-bar">
        <tr>
            <td><strong>بیمار:</strong> {{ $patient['name'] ?? '---' }}</td>
            <td><strong>موبایل:</strong> {{ $patient['phone'] ?? '—' }}</td>
        </tr>
    </table>

    <div class="page-banner">صورتحساب درمانی &mdash; سند داخلی &mdash; فاقد اعتبار رسمی</div>

    {{-- items --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:13%">کد خدمت</th>
                <th style="width:38%; text-align:right; padding-right:6px;">شرح خدمت / آزمایش</th>
                <th style="width:8%">تعداد</th>
                <th style="width:18%">پزشک معالج</th>
                <th style="width:18%">مبلغ (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $row['code'] ?? '—' }}</td>
                <td class="svc">{{ $row['service_name'] ?? '' }}</td>
                <td>{{ $row['quantity'] ?? $row['count'] ?? 1 }}</td>
                <td>{{ $row['doctor_name'] ?? '—' }}</td>
                <td>{{ number_format((float)($row['amount'] ?? 0)) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:12px; text-align:center; color:#a0aec0;">
                    هیچ ردیف خدمتی ثبت نشده است.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- totals --}}
    <div class="sum-wrap">
        <table class="sum-table">
            <tr>
                <td>مجموع خدمات</td>
                <td class="num">{{ number_format((float)($invoice['total_amount'] ?? 0)) }} ریال</td>
            </tr>
            @if(($invoice['discount'] ?? 0) > 0)
            <tr>
                <td style="color:#e53e3e;">تخفیف</td>
                <td class="num" style="color:#e53e3e;">{{ number_format($invoice['discount']) }} ریال</td>
            </tr>
            @endif
            @if(($invoice['insurance_share'] ?? 0) > 0)
            <tr>
                <td style="color:#2b6cb0;">سهم بیمه</td>
                <td class="num" style="color:#2b6cb0;">{{ number_format($invoice['insurance_share']) }} ریال</td>
            </tr>
            @endif
            <tr class="payable">
                <td>مبلغ قابل پرداخت</td>
                <td class="num">{{ number_format((float)($invoice['payable_amount'] ?? 0)) }} ریال</td>
            </tr>
        </table>
    </div>

    {{-- footer --}}
    <div class="doc-footer">
        <span>
            <strong style="color:#0f766e;">www.TehranEP.center</strong>
            &nbsp;|&nbsp; Tel: +98(21) 88887270
        </span>
        <span>این سند داخلی است و فاقد مهر و امضا می‌باشد. | {{ now()->format('Y-m-d H:i') }}</span>
    </div>

</body>

</html>