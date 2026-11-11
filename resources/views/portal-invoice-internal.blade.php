<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>فاکتور داخلی</title>
    <style>
        @page {
            size: A5 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            margin: 0;
            direction: rtl;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 14pt;
            margin: 0 0 2px;
        }

        .header p {
            font-size: 9pt;
            margin: 0;
            color: #555;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            font-size: 10pt;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 10px;
        }

        th {
            background: #f0f0f0;
            border: 1px solid #bbb;
            padding: 4px 6px;
        }

        td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: center;
        }

        .totals {
            text-align: left;
            font-size: 10pt;
            line-height: 1.8;
        }

        .totals strong {
            display: inline-block;
            min-width: 110px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            color: #777;
            border-top: 1px solid #ccc;
            padding-top: 4px;
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

    <div class="header">
        <h1>مرکز آریتمی تهران</h1>
        <p>فاکتور داخلی</p>
    </div>

    <div class="meta">
        <span>بیمار: {{ $patientName }}</span>
        <span>موبایل: {{ $mobile }}</span>
        <span>شماره فاکتور: {{ $invoiceNumber }}</span>
        <span>تاریخ: {{ \Morilog\Jalali\Jalalian::fromCarbon($createdAt)->format('Y/m/d') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ردیف</th>
                <th>شرح خدمت</th>
                <th>تعداد</th>
                <th>مبلغ واحد (ریال)</th>
                <th>جمع (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->price) }}</td>
                <td>{{ number_format($item->price * $item->quantity) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <strong>جمع کل:</strong> {{ number_format($totalAmount) }} ریال<br>
        <strong>تخفیف:</strong> {{ number_format($discount) }} ریال<br>
        <strong>مبلغ قابل پرداخت:</strong> {{ number_format($payableAmount) }} ریال
    </div>

    <div class="footer">
        این فاکتور صرفاً جهت استفاده داخلی مرکز می‌باشد.
    </div>

</body>

</html>