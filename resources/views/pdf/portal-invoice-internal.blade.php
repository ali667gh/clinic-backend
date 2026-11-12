{{-- portal-invoice-internal.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>فاکتور داخلی</title>
    <style>
        @page {
            margin-top: 10mm;
            margin-bottom: 10mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }

        body {
            font-family: 'dejavusans', 'vazir', 'tahoma', sans-serif;
            font-size: 11.5px;
            color: #1a202c;
            direction: rtl;
            text-align: right;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* ── header ── */
        .head-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.2px solid #cbd5e0;
            padding-bottom: 6px;
        }

        .head-table td {
            padding: 4px 6px 8px 6px;
            vertical-align: middle;
        }

        .clinic-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a365d;
        }

        .clinic-sub {
            font-size: 11.5px;
            color: #4a5568;
            margin-top: 2px;
        }

        .meta {
            font-size: 10.5px;
            color: #2d3748;
            text-align: left;
            line-height: 1.65;
        }

        /* ── patient bar ── */
        .patient-bar {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #cbd5e0;
            background-color: #f7fafc;
            margin-bottom: 10px;
            margin-top: 6px;
        }

        .patient-bar td {
            padding: 7px 10px;
            font-size: 11.5px;
        }

        /* ── page banner ── */
        .page-banner {
            background-color: #e8edf3;
            color: #2d3748;
            font-size: 10.5px;
            font-weight: bold;
            padding: 5px 10px;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        /* ── invoice title ── */
        .invoice-title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            color: #1a365d;
            margin: 6px 0 10px 0;
        }

        /* ── items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .items-table th {
            background-color: #edf2f7;
            color: #2d3748;
            border: 1.5px solid #cbd5e0;
            padding: 7px 5px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
        }

        .items-table td {
            border: 1.5px solid #cbd5e0;
            padding: 6px 5px;
            font-size: 11px;
            text-align: center;
        }

        .items-table td.svc-name {
            text-align: right;
        }

        /* ── sum table ── */
        .sum-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sum-table td {
            border: 1.5px solid #cbd5e0;
            padding: 7px 10px;
            font-size: 11.5px;
        }

        /* ── footer ── */
        .doc-footer {
            margin-top: 14px;
            border-top: 1px dashed #cbd5e0;
            padding-top: 6px;
            font-size: 9.5px;
            color: #718096;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>

    @php
    // ── قیمت یک آیتم ── چندین کلید احتمالی را امتحان می‌کند
    function itemPrice(array $item): float {
    foreach (['price','amount','unit_price','unitPrice','fee','cost'] as $k) {
    if (isset($item[$k]) && $item[$k] > 0) return (float)$item[$k];
    }
    return 0;
    }

    function itemCount(array $item): int {
    foreach (['count','quantity','qty'] as $k) {
    if (isset($item[$k])) return (int)$item[$k];
    }
    return 1;
    }

    function itemTotal(array $item): float {
    foreach (['total','total_price','totalPrice','subtotal','line_total'] as $k) {
    if (isset($item[$k]) && $item[$k] > 0) return (float)$item[$k];
    }
    return itemCount($item) * itemPrice($item);
    }

    function itemTitle(array $item): string {
    foreach (['serviceTitle','service_name','name','title','label','description'] as $k) {
    if (!empty($item[$k])) return $item[$k];
    }
    return '—';
    }

    function itemCode(array $item): string {
    foreach (['code','service_code','serviceCode'] as $k) {
    if (!empty($item[$k])) return $item[$k];
    }
    return '—';
    }

    function itemDoctor(array $item): string {
    foreach (['doctor','doctor_name','doctorName','physician'] as $k) {
    if (!empty($item[$k])) return $item[$k];
    }
    return '—';
    }
    @endphp

    {{-- ── هدر ── --}}
    <table class="head-table">
        <tr>
            <td style="width:16%; text-align:left; padding-left:8px;">
                @if(!empty($logo))
                <img src="{{ $logo }}" alt="لوگو" style="height:55px; width:auto;">
                @endif
            </td>
            <td style="width:54%; text-align:right;">
                <div class="clinic-title">مرکز آریتمی تهران</div>
                <div class="clinic-sub">تشخیص و درمان اختلالات ضربان قلب</div>
            </td>
            <td class="meta" style="width:30%;">
                <div><strong>شماره پرونده:</strong> #{{ $archive->id ?? '---' }}</div>
                <div><strong>شماره فاکتور:</strong> {{ $invoiceNumber ?? '---' }}</div>
                <div>
                    <strong>تاریخ صدور:</strong>
                    {{ $createdAt
                    ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($createdAt))->format('Y/m/d')
                    : '---' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- ── بار بیمار ── --}}
    <table class="patient-bar">
        <tr>
            <td><strong>بیمار:</strong> {{ $patientName ?? '---' }}</td>
            <td><strong>شماره موبایل:</strong> {{ $mobile ?: '---' }}</td>
        </tr>
    </table>

    <div class="invoice-title">صورتحساب درمانی</div>
    <div class="page-banner">سند داخلی — فاقد اعتبار رسمی</div>

    {{-- ── جدول اقلام ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:12%;">کد خدمت</th>
                <th style="width:38%;">شرح خدمت / آزمایش</th>
                <th style="width:10%;">تعداد</th>
                <th style="width:17%;">پزشک معالج</th>
                <th style="width:18%;">مبلغ (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ itemCode($item) }}</td>
                <td class="svc-name">{{ itemTitle($item) }}</td>
                <td>{{ itemCount($item) }}</td>
                <td>{{ itemDoctor($item) }}</td>
                <td>{{ number_format(itemTotal($item)) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="padding:12px; color:#a0aec0; text-align:center;">
                    هیچ ردیف خدمتی ثبت نشده است.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── خلاصه مالی ── --}}
    <table style="width:100%; margin-top:12px; border-collapse:collapse;">
        <tr>
            <td style="width:50%;"></td>
            <td style="width:50%; vertical-align:top;">
                <table class="sum-table">
                    <tr>
                        <td style="color:#4a5568;">مجموع خدمات:</td>
                        <td style="text-align:left;">
                            <strong>{{ number_format($totalAmount ?? 0) }}</strong> ریال
                        </td>
                    </tr>
                    @if(($discount ?? 0) > 0)
                    <tr>
                        <td style="color:#e53e3e;">تخفیف:</td>
                        <td style="text-align:left; color:#e53e3e;">
                            {{ number_format($discount) }} ریال
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="color:#15803d; font-weight:bold;">مبلغ قابل پرداخت:</td>
                        <td style="text-align:left; color:#15803d; font-weight:bold;">
                            {{ number_format($payableAmount ?? 0) }} ریال
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── فوتر ── --}}
    <div class="doc-footer">
        <span>این سند یک نسخه داخلی است و فاقد مهر و امضا می‌باشد.</span>
        <span>{{ now()->format('Y-m-d H:i') }}</span>
    </div>

</body>

</html>