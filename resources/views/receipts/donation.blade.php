<!--  --><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donation Receipt</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 0;
            padding: 24px;
            background: #f8fafc;
        }

        .card {
            background: #ffffff;
            border: 1px solid #dbe3ea;
            border-radius: 14px;
            padding: 28px;
        }

        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 6px;
        }

        .subtitle {
            font-size: 11px;
            color: #475569;
            margin: 0;
        }

        .amount-box {
            margin: 20px 0;
            padding: 18px;
            background: #ecfeff;
            border: 1px solid #99f6e4;
            border-radius: 12px;
        }

        .amount-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #0f766e;
            margin-bottom: 6px;
        }

        .amount-value {
            font-size: 28px;
            font-weight: 700;
            color: #115e59;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        td {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .label {
            width: 38%;
            color: #64748b;
        }

        .value {
            font-weight: 600;
            color: #0f172a;
        }

        .footer {
            margin-top: 22px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
            color: #475569;
            font-size: 11px;
            line-height: 1.6;
        }

        .status {
            display: inline-block;
            padding: 4px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 class="title">Donation Receipt</h1>
            <p class="subtitle">Rotary Club acknowledgment receipt for successful donation.</p>
        </div>

        <div class="amount-box">
            <div class="amount-label">Received Amount</div>
            <p class="amount-value">Rs. {{ number_format((float) $receiptData['amount'], 2) }}</p>
        </div>

        <table>
            <tr>
                <td class="label">Receipt Number</td>
                <td class="value">{{ $receiptData['receipt_no'] }}</td>
            </tr>
            <tr>
                <td class="label">Transaction ID</td>
                <td class="value">{{ $receiptData['transaction_id'] }}</td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value"><span class="status">{{ $receiptData['status'] }}</span></td>
            </tr>
            <tr>
                <td class="label">Donor Name</td>
                <td class="value">{{ $receiptData['donor_name'] }}</td>
            </tr>
            <tr>
                <td class="label">Phone Number</td>
                <td class="value">{{ $donation->mobile_no }}</td>
            </tr>
            <tr>
                <td class="label">Donation Date</td>
                <td class="value">{{ $receiptData['date'] }} {{ $receiptData['time'] ? 'at ' . $receiptData['time'] : '' }}</td>
            </tr>
            <!-- <tr>
                <td class="label">Payment Method</td>
                <td class="value">{{ $receiptData['payment_method'] }}</td>
            </tr> -->
            @if(!empty($receiptData['donated_for']))
            <tr>
                <td class="label">Donated For</td>
                <td class="value">{{ $receiptData['donated_for'] }}</td>
            </tr>
            @endif
            @if(!empty($receiptData['member_id']))
            <tr>
                <td class="label">Member ID</td>
                <td class="value">{{ $receiptData['member_id'] }}</td>
            </tr>
            @endif
        </table>

        <div class="footer">
            This is a system-generated receipt. Please keep this PDF for your records and future reference.
        </div>
    </div>
</body>
</html>
