<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4; margin: 0; }
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
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        .header {
            background: #000000;
            color: #fff;
            padding: 14px 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }
        .header .sub {
            font-size: 8px;
            color: #ffffff;
            margin-top: 2px;
        }
        .body {
            padding: 16px 20px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .meta-table td {
            padding-bottom: 8px;
            border-bottom: 1.5px dashed #d1d5db;
            vertical-align: top;
            width: 50%;
        }
        .meta-table td:last-child {
            text-align: right;
        }
        .meta-table .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #5a5c61;
            letter-spacing: 0.5px;
        }
        .meta-table .val {
            font-size: 10px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 1px;
        }

        .pin-box {
            background: #f0fdf4;
            border: 1.5px solid #22c55e;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
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
        .data-table .cell {
            background: #f9fafb;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .data-table .cell.yellow {
            background: #fef3c7;
        }
        .data-table .cell.green {
            background: #d8fec7;
        }
        .data-table .cell .lbl {
            font-size: 7px;
            text-transform: uppercase;
            color: #5a5c61;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .data-table .cell .val {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .data-table .cell.yellow .val {
            color: #92400e;
        }
        .data-table .cell .note {
            font-size: 8px;
            color: #374151;
            font-weight: 500;
            line-height: 1.3;
        }

        .rows {
            margin-bottom: 12px;
        }
        .row {
            padding: 4px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 9px;
        }
        .row:last-child { border-bottom: none; }
        .row .rl { color: #6b7280; }
        .row .rv { font-weight: 600; color: #0f172a; }
        .row .benefit {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 7px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            margin: 1px 2px 1px 0;
        }

        .desc-section-title {
            font-size: 7px;
            text-transform: uppercase;
            color: #5a5c61;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            margin-top: 4px;
        }
        .desc-box {
            background: #f9fafb;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
            font-size: 8px;
            color: #4b5563;
            line-height: 1.5;
        }
        .desc-box:last-child {
            margin-bottom: 0;
        }
        .desc-box strong { color: #0f172a; }

        .footer {
            text-align: center;
            padding: 8px;
            font-size: 7px;
            color: #5a5c61;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <img src="{{ public_path('logo.png') }}" style="width: 100px; height: auto;" alt="MK Network">
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
                            <div class="val">{{ $transaction->send_currency ?? 'GBP' }} {{ number_format($transaction->send_value ?? $transaction->amount, 2) }}</div>
                        </div>
                    </td>
                    <td style="width:33%;">
                        <div class="cell yellow">
                            <div class="lbl">{{ $transaction->redemption_type === 'ReadReceipt' ? 'PIN Value' : 'Customer Gets' }}</div>
                            <div class="val">{{ $transaction->receive_currency ?? 'GBP' }} {{ number_format($transaction->receive_value ?? $transaction->amount, 2) }}</div>
                        </div>
                    </td>
                </tr>
            </table>
            
            <!-- Number row -->
            @if($transaction->redemption_type !== 'ReadReceipt')
                <table class="data-table">
                    <tr>
                        <td style="width:33%;">
                            <div class="cell">
                                <div class="lbl">Number</div>
                                <div class="note">
                                        +{{ $transaction->mobile_number }}
                                </div>
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
                    <span class="rl">DingConnect Ref</span>
                    <span class="rv">{{ $transaction->ding_transaction_id ?? '-' }}</span>
                </div>
                <div class="row">
                    <span class="rl">Order Ref</span>
                    <span class="rv">{{ $transaction->ding_order_reference ?? '-' }}</span>
                </div>
            </div>

            @if($transaction->description_markdown || $transaction->readmore_markdown)
            <div class="desc-section-title">{{ $transaction->redemption_type === 'ReadReceipt' ? 'How to Redeem' : 'Product Information' }}</div>
            @if($transaction->description_markdown)
            <div class="desc-box">{!! $transaction->description_markdown !!}</div>
            @endif
            @if($transaction->readmore_markdown)
            <div class="desc-box">{!! $transaction->readmore_markdown !!}</div>
            @endif
            @endif
        </div>

        <div class="footer">
            <p>MK Network &middot; Powered by DingConnect</p>
            <p style="margin-top:2px;">This is a computer-generated receipt.</p>
        </div>
    </div>
</body>
</html>
