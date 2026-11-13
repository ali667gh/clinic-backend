{{-- portal-invoice-internal.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتور داخلی</title>
    <style>
        :root {
            --surface: #f7f7f5;
            --border: #d2cec6;
            --text: #1a1a18;
            --muted: #6a6760;
            --accent: #1b4d82;
            /* oklch حذف شد — سازگار با همه مرورگرها */
            --accent-bg: #e8eef5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Vazirmatn', Vazir, Tahoma, 'Arial Unicode MS', sans-serif;
            font-size: 11px;
            color: var(--text);
            background: #fff;
            width: 148mm;
            min-height: 210mm;
            padding: 10mm 12mm;
            line-height: 1.65;
        }

        /* ── header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--accent);
        }

        .header-sub {
            font-size: 10px;
            color: var(--muted);
            margin-top: 2px;
        }

        .invoice-no {
            text-align: left;
            font-size: 10px;
            color: var(--muted);
            line-height: 1.8;
        }

        .invoice-no strong {
            display: block;
            font-size: 12px;
            color: var(--text);
        }

        /* ── meta grid ── */
        .meta-grid {
            display: grid;
            grid-template-columns: auto 1fr auto 1fr;
            gap: 5px 12px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 8px 10px;
            margin-bottom: 10px;
            font-size: 10.5px;
        }

        .meta-grid .lbl {
            color: var(--muted);
        }

        .meta-grid .val {
            font-weight: 600;
        }

        /* ── internal banner ── */
        .banner {
            background: var(--accent-bg);
            color: var(--accent);
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            text-align: center;
            letter-spacing: 0.03em;
        }

        /* ── items table ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }

        table.items thead th {
            background: var(--accent);
            color: #fff;
            padding: 6px 7px;
            text-align: center;
            font-weight: 600;
            border: 1px solid var(--accent);
        }

        table.items thead th.svc {
            text-align: right;
        }

        table.items tbody tr:nth-child(even) {
            background: var(--surface);
        }

        table.items tbody td {
            padding: 5px 7px;
            border: 1px solid var(--border);
            text-align: center;
            vertical-align: middle;
        }

        table.items tbody td.svc {
            text-align: right;
        }

        table.items tbody td.num {
            font-variant-numeric: tabular-nums;
        }

        /* ── totals ── */
        table.totals {
            width: 52%;
            margin-right: auto;
            /* چپ — چون RTL */
            margin-left: 0;
            border-collapse: collapse;
            font-size: 10.5px;
            border: 1px solid var(--border);
            border-radius: 5px;
            overflow: hidden;
        }

        table.totals td {
            padding: 6px 10px;
            border-bottom: 1px solid var(--border);
        }

        table.totals tr:nth-child(even) td {
            background: var(--surface);
        }

        table.totals tr.payable td {
            background: var(--accent) !important;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
        }

        table.totals td.num {
            text-align: left;
            font-variant-numeric: tabular-nums;
        }

        /* ── footer ── */
        .footer {
            margin-top: 16px;
            border-top: 1px dashed var(--border);
            padding-top: 6px;
            font-size: 9.5px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                padding: 8mm 10mm;
            }

            @page {
                size: A5 portrait;
                margin: 0;
            }
        }
    </style>
</head>

<body>

    @php
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

    {{-- header --}}
    <div class="header">
        <div>
            <div class="header-title">مرکز آریتمی تهران</div>
            <div class="header-sub">تشخیص و درمان اختلالات ضربان قلب</div>
        </div>
        <div class="invoice-no">
            <span class="lbl">شماره پرونده</span>
            <strong>#{{ $archive->id ?? '---' }}</strong>
            <span class="lbl">شماره فاکتور</span>
            <strong>{{ $invoiceNumber ?? '---' }}</strong>
            <span class="lbl">تاریخ صدور</span>
            <strong>
                {{ $createdAt
                ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($createdAt))->format('Y/m/d')
                : '---' }}
            </strong>
        </div>
    </div>

    {{-- patient meta --}}
    <div class="meta-grid">
        <span class="lbl">بیمار</span>
        <span class="val">{{ $patientName ?? '---' }}</span>
        <span class="lbl">موبایل</span>
        <span class="val">{{ $mobile ?: '—' }}</span>
    </div>

    <div class="banner">صورتحساب درمانی &mdash; سند داخلی &mdash; فاقد اعتبار رسمی</div>

    {{-- items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:11%">کد خدمت</th>
                <th class="svc" style="width:33%">شرح خدمت / آزمایش</th>
                <th style="width:9%">تعداد</th>
                <th style="width:18%">پزشک معالج</th>
                <th style="width:18%">مبلغ واحد (ریال)</th>
                <th style="width:16%">جمع (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ itemCode($item) }}</td>
                <td class="svc">{{ itemTitle($item) }}</td>
                <td>{{ itemCount($item) }}</td>
                <td>{{ itemDoctor($item) }}</td>
                <td class="num">{{ number_format(itemPrice($item)) }}</td>
                <td class="num">{{ number_format(itemTotal($item)) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding:12px;text-align:center;color:var(--muted)">
                    هیچ ردیف خدمتی ثبت نشده است.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- totals --}}
    <table class="totals">
        <tr>
            <td>مجموع خدمات</td>
            <td class="num">{{ number_format($totalAmount ?? 0) }} ریال</td>
        </tr>
        @if(($discount ?? 0) > 0)
        <tr>
            <td style="color:#c0392b">تخفیف</td>
            <td class="num" style="color:#c0392b">{{ number_format($discount) }} ریال</td>
        </tr>
        @endif
        <tr class="payable">
            <td>مبلغ قابل پرداخت</td>
            <td class="num">{{ number_format($payableAmount ?? 0) }} ریال</td>
        </tr>
    </table>

    {{-- footer --}}
    <div class="footer">
        <span>این سند یک نسخه داخلی است و فاقد مهر و امضا می‌باشد.</span>
        <span>{{ now()->format('Y-m-d H:i') }}</span>
    </div>

</body>

</html>