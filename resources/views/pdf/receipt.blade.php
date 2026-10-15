<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10px;
            color: #1a1a2e;
            background: #fff;
            padding: 24px 28px;
        }

        .receipt {
            max-width: 460px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .header {
            background: #f8fafc;
            color: #0f172a;
            padding: 16px 20px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 6px;
        }

        .header h1 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #0f172a;
        }

        .header .sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .body {
            padding: 20px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .meta-table td {
            padding-bottom: 8px;
            border-bottom: 1.5px dashed #cbd5e1;
            vertical-align: top;
            width: 50%;
        }

        .meta-table td:last-child {
            text-align: right;
        }

        .meta-table .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        .meta-table .val {
            font-size: 10px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 2px;
        }

        .pin-box {
            background: #f0fdf4;
            border: 1.5px solid #22c55e;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 16px;
        }

        .pin-box .pin-label {
            font-size: 7px;
            text-transform: uppercase;
            color: #15803d;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 3px;
        }

        .pin-box .pin-val {
            font-size: 15px;
            font-weight: 800;
            color: #15803d;
            letter-spacing: 2px;
            line-height: 1.4;
            word-break: break-all;
        }

        .pin-box .pin-note {
            font-size: 7px;
            color: #166534;
            margin-top: 4px;
            font-style: italic;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .data-table td {
            padding: 0 4px;
            vertical-align: top;
        }

        .data-table td:first-child {
            padding-left: 0;
        }

        .data-table td:last-child {
            padding-right: 0;
        }

        .cell {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .cell.yellow {
            background: #fef9c3;
            border-color: #fef08a;
        }

        .cell.green {
            background: #dcfce7;
            border-color: #bbf7d0;
        }

        .cell .lbl {
            font-size: 7px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .cell .val {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .cell.green .val {
            color: #15803d;
        }

        .cell.yellow .val {
            color: #a16207;
        }

        .cell.green .lbl {
            color: #166534;
        }

        .cell.yellow .lbl {
            color: #854d0e;
        }

        .cell .note {
            font-size: 11px;
            color: #0f172a;
            font-weight: 700;
            line-height: 1.3;
        }

        .rows {
            margin-bottom: 16px;
            margin-top: 4px;
        }

        .row {
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9px;
            display: flex;
            justify-content: space-between;
        }

        .row:last-child {
            border-bottom: none;
        }

        .row .rl {
            color: #64748b;
        }

        .row .rv {
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }

        .row .benefit {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 7px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            margin: 1px 0 1px 2px;
        }

        .desc-section-title {
            font-size: 7px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            margin-top: 4px;
            font-weight: 700;
        }

        .desc-box {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 6px;
            font-size: 8px;
            color: #475569;
            line-height: 1.5;
        }

        .desc-box:last-child {
            margin-bottom: 0;
        }

        .desc-box strong {
            color: #0f172a;
        }

        .footer {
            text-align: center;
            padding: 12px;
            font-size: 7px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="header">
            <div class="logo-container">
                <img src="{{ public_path('logo.png') }}" style="width: 100px; height: auto;"
                    alt="MK All Network Communications">
            </div>
            <div class="sub">Mobile Recharge Receipt</div>
        </div>

        <div class="body">
            <!-- Meta row -->
            <table class="meta-table">
                <tr>
                    <td>
                        <div class="label">Receipt No.</div>
                        <div class="val">{{ $transaction->receipt_number }}</div>
                    </td>
                    <td style="text-align:right;">
                        <div class="label">Date</div>
                        <div class="val">{{ $transaction->created_at->format('d M Y, H:i') }}</div>
                    </td>
                </tr>
            </table>

            @if($transaction->redemption_type === 'ReadReceipt' && $transaction->receipt_text)
                <div class="pin-box">
                    <div class="pin-label">PIN / Voucher Code</div>
                    <div class="pin-val">{!! nl2br(e($transaction->receipt_text)) !!}</div>
                    <div class="pin-note"></div>
                </div>
            @endif

            <!-- Three data columns -->
            <table class="data-table">
                <tr>
                    <td style="width:33%;">
                        <div class="cell green">
                            <div class="lbl">{{ $transaction->operator?->name ?? 'Mobile Top-Up' }}</div>
                            <div class="val">{{ $transaction->display_text ?? $transaction->sku_code }}</div>
                        </div>
                    </td>
                    <td style="width:33%;">
                        <div class="cell">
                            <div class="lbl">You Paid</div>
                            <div class="val">{{ $transaction->send_currency ?? 'GBP' }}
                                {{ number_format($transaction->send_value ?? $transaction->amount, 2) }}</div>
                        </div>
                    </td>
                    <td style="width:33%;">
                        <div class="cell yellow">
                            <div class="lbl">
                                {{ $transaction->redemption_type === 'ReadReceipt' ? 'PIN Value' : 'Customer Gets' }}
                            </div>
                            <div class="val">{{ $transaction->receive_currency ?? 'GBP' }}
                                {{ number_format($transaction->receive_value ?? $transaction->amount, 2) }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Number row -->
            @if($transaction->redemption_type !== 'ReadReceipt')
                <table class="data-table">
                    <tr>
                        <td style="width:100%;">
                            <div class="cell">
                                <div class="lbl">Mobile Number</div>
                                <div class="note">+{{ $transaction->mobile_number }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            @endif

            <!-- Details rows -->
            <div class="rows">
                <div class="row">
                    <span class="rl">SKU Code</span>
                    <span class="rv">{{ $transaction->sku_code ?? '-' }}</span>
                </div>
                <div class="row">
                    <span class="rl">Redemption</span>
                    <span class="rv">{{ $transaction->redemption_type ?? 'Immediate' }}</span>
                </div>
                @if($transaction->validity_period)
                    <div class="row">
                        <span class="rl">Validity</span>
                        <span class="rv">{{ $transaction->validity_period }}</span>
                    </div>
                @endif
                @if($transaction->benefits && count($transaction->benefits) > 0)
                    <div class="row">
                        <span class="rl">Benefits</span>
                        <span class="rv">
                            @foreach($transaction->benefits as $b)
                                <span class="benefit">{{ $b }}</span>
                            @endforeach
                        </span>
                    </div>
                @endif
                <div class="row">
                    <span class="rl">Transaction Ref</span>
                    <span class="rv">{{ $transaction->ding_transaction_id ?? '-' }}</span>
                </div>
                <div class="row">
                    <span class="rl">Order Ref</span>
                    <span class="rv">{{ $transaction->ding_order_reference ?? '-' }}</span>
                </div>
            </div>

            @if($transaction->description_markdown || $transaction->readmore_markdown)
                <div class="desc-section-title">
                    {{ $transaction->redemption_type === 'ReadReceipt' ? 'How to Redeem' : 'Product Information' }}</div>
                @if($transaction->description_markdown)
                    <div class="desc-box">{!! $transaction->description_markdown !!}</div>
                @endif
                @if($transaction->readmore_markdown)
                    <div class="desc-box">{!! $transaction->readmore_markdown !!}</div>
                @endif
            @endif
        </div>

        <div class="footer">
            <p>MK All Network Communications.</p>
            <p style="margin-top:2px;">This is a computer-generated receipt.</p>
        </div>
    </div>
</body>

</html>