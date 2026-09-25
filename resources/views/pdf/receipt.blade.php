<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4; margin: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #fff;
            padding: 28px 32px;
        }
        .receipt {
            max-width: 480px;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background: #0f172a;
            color: #fff;
            padding: 18px 24px;
            text-align: center;
        }
        .header h1 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }
        .header .sub {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .body {
            padding: 20px 24px;
        }
        .meta {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1.5px dashed #d1d5db;
            margin-bottom: 14px;
        }
        .meta .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
        }
        .meta .val {
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 1px;
        }

        .pin-box {
            background: #f0fdf4;
            border: 1.5px solid #22c55e;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .pin-box .pin-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #15803d;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .pin-box .pin-val {
            font-size: 18px;
            font-weight: 800;
            color: #15803d;
            letter-spacing: 2px;
            line-height: 1.4;
            word-break: break-all;
        }
        .pin-box .pin-note {
            font-size: 8px;
            color: #166534;
            margin-top: 6px;
            font-style: italic;
        }

        .number-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            text-align: center;
        }
        .number-box .num-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #3b82f6;
            font-weight: 600;
        }
        .number-box .num-val {
            font-size: 16px;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: 1px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .box {
            background: #f9fafb;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: center;
        }
        .box .lbl {
            font-size: 8px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
        }
        .box .val {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }
        .box.highlight {
            background: #fef3c7;
        }
        .box.highlight .val {
            color: #92400e;
        }

        .section-title {
            font-size: 8px;
            text-transform: uppercase;
            color: #9ca3af;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            margin-top: 4px;
        }
        .plan-row {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f9fafb;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 14px;
        }
        .plan-row .logo {
            width: 36px;
            height: 36px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .plan-row .logo img {
            max-width: 26px;
            max-height: 26px;
        }
        .plan-row .pname {
            font-weight: 700;
            font-size: 12px;
            color: #0f172a;
        }
        .plan-row .pdesc {
            font-size: 9px;
            color: #6b7280;
            margin-top: 1px;
        }

        .rows {
            margin-bottom: 14px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10px;
        }
        .row:last-child { border-bottom: none; }
        .row .rl { color: #6b7280; }
        .row .rv { font-weight: 600; color: #0f172a; text-align: right; max-width: 55%; word-break: break-word; }

        .desc-box {
            background: #f9fafb;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 14px;
            font-size: 9px;
            color: #4b5563;
            line-height: 1.6;
            max-height: 80px;
            overflow: hidden;
        }
        .desc-box strong { color: #0f172a; }

        .benefits {
            margin-bottom: 14px;
        }
        .benefit {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 8px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            margin: 1px 3px 1px 0;
        }

        .footer {
            text-align: center;
            padding: 10px;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>MK NETWORK</h1>
            <div class="sub">Mobile Recharge Receipt</div>
        </div>

        <div class="body">
            <div class="meta">
                <div>
                    <div class="label">Receipt No.</div>
                    <div class="val">{{ $transaction->receipt_number }}</div>
                </div>
                <div style="text-align:right;">
                    <div class="label">Date</div>
                    <div class="val">{{ $transaction->created_at->format('d M Y, H:i') }}</div>
                </div>
            </div>

            @if($transaction->redemption_type === 'ReadReceipt' && $transaction->receipt_text)
            <div class="pin-box">
                <div class="pin-label">PIN / Voucher Code</div>
                <div class="pin-val">{!! nl2br(e($transaction->receipt_text)) !!}</div>
                <div class="pin-note">Share with customer. Any SIM of this provider can redeem this PIN.</div>
            </div>
            @endif

            <div class="plan-row">
                <div class="logo">
                    @if($transaction->operator && $transaction->operator->logo_url)
                    <img src="{{ $transaction->operator->logo_url }}" alt="">
                    @else
                    <span style="font-size:18px;">📱</span>
                    @endif
                </div>
                <div>
                    <div class="pname">{{ $transaction->operator?->name ?? 'Mobile Top-Up' }}</div>
                    <div class="pdesc">{{ $transaction->display_text ?? $transaction->sku_code }}</div>
                </div>
            </div>

            @if($transaction->redemption_type === 'Immediate')
            <div class="number-box">
                <div class="num-label">Recharged Number</div>
                <div class="num-val">+{{ $transaction->mobile_number }}</div>
            </div>
            @endif

            <div class="grid">
                <div class="box">
                    <div class="lbl">You Paid</div>
                    <div class="val">{{ $transaction->send_currency ?? 'GBP' }} {{ number_format($transaction->send_value ?? $transaction->amount, 2) }}</div>
                </div>
                <div class="box highlight">
                    <div class="lbl">{{ $transaction->redemption_type === 'ReadReceipt' ? 'PIN Value' : 'Customer Gets' }}</div>
                    <div class="val">{{ $transaction->receive_currency ?? 'GBP' }} {{ number_format($transaction->receive_value ?? $transaction->amount, 2) }}</div>
                </div>
            </div>

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
                <div class="row" style="flex-direction:column;">
                    <span class="rl">Benefits</span>
                    <span class="rv" style="text-align:left; margin-top:3px;">
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
            <div class="section-title">Product Information</div>
            <div class="desc-box">
                {!! $transaction->readmore_markdown ?: $transaction->description_markdown !!}
            </div>
            @endif
        </div>

        <div class="footer">
            <p>MK Network &middot; Powered by DingConnect</p>
            <p style="margin-top:2px;">This is a computer-generated receipt.</p>
        </div>
    </div>
</body>
</html>
