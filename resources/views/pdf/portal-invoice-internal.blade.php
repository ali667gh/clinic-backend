<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتور داخلی</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap');

        :root {
            --surface: #f8f8f6;
            --border: #d4d0c8;
            --text: #1a1a18;
            --muted: #6b6860;
            --accent: oklch(42% 0.12 240);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            font-size: 11px;
            color: var(--text);
            background: #fff;
            width: 148mm;
            min-height: 210mm;
            padding: 10mm 12mm;
            line-height: 1.6;
        }

        /* ── header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .header-title {
            font-size: 14px;
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
        }

        .invoice-no strong {
            display: block;
            font-size: 12px;
            color: var(--text);
        }

        /* ── patient row ── */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 10px;
            font-size: 10.5px;
        }

        .meta-grid .label {
            color: var(--muted);
        }

        .meta-grid .value {
            font-weight: 600;
        }

        /* ── items table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 10px;
        }

        thead th {
            background: var(--accent);
            color: #fff;
            padding: 5px 7px;
            text-align: right;
            font-weight: 600;
        }

        tbody tr:nth-child(even) {
            background: var(--surface);
        }

        tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        td.num {
            text-align: left;
            font-variant-numeric: tabular-nums;
        }

        /* ── totals ── */
        .totals {
            margin-right: auto;
            width: 55%;
            margin-left: 0;
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            font-size: 10.5px;
        }

        .totals tr td {
            padding: 5px 10px;
        }

        .totals tr:nth-child(even) {
            background: var(--surface);
        }

        .totals .payable {
            background: var(--accent) !important;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
        }

        /* ── footer ── */
        .footer {
            margin-top: 14px;
            border-top: 1px dashed var(--border);
            padding-top: 6px;
            font-size: 9.5px;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                width: 148mm;
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

    <div class="header">
        <div>
            <div class="header-title">فاکتور داخلی آزمایشگاه</div>
            <div class="header-sub">تهران — سند داخلی</div>
        </div>
        <div class="invoice-no">
            <span class="label">شماره فاکتور</span>
            <strong>#{{ $invoiceNumber }}</strong>
        </div>
    </div>

    <div class="meta-grid">
        <span class="label">نام بیمار</span>
        <span class="value">{{ $patientName }}</span>
        <span class="label">موبایل</span>
        <span class="value">{{ $mobile ?: '—' }}</span>
        <span class="label">تاریخ صدور</span>
        <span class="value">
            {{ $createdAt ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($createdAt))->format('Y/m/d') : '—' }}
        </span>
        <span class="label">شناسه پرونده</span>
        <span class="value">#{{ $archive->id }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>شرح خدمت</th>
                <th>تعداد</th>
                <th>مبلغ واحد (ریال)</th>
                <th>جمع (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $item)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $item['name'] ?? $item['title'] ?? '—' }}</td>
                <td class="num">{{ $item['quantity'] ?? 1 }}</td>
                <td class="num">{{ number_format($item['price'] ?? $item['amount'] ?? 0) }}</td>
                <td class="num">{{ number_format(($item['quantity'] ?? 1) * ($item['price'] ?? $item['amount'] ?? 0)) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:12px">آیتمی ثبت نشده</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>جمع کل</td>
            <td class="num">{{ number_format($totalAmount) }} ریال</td>
        </tr>
        <tr>
            <td>تخفیف</td>
            <td class="num">{{ number_format($discount) }} ریال</td>
        </tr>
        <tr class="payable">
            <td>مبلغ قابل پرداخت</td>
            <td class="num">{{ number_format($payableAmount) }} ریال</td>
        </tr>
    </table>

    <div class="footer">
        <span>این سند یک نسخه داخلی است و فاقد مهر و امضا می‌باشد.</span>
        <span>{{ now()->format('Y-m-d H:i') }}</span>
    </div>

</body>

</html>