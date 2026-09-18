<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job ticket {{ $order->number }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 11px; color: #111; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .number { font-size: 20px; font-weight: bold; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        .qr { width: 110px; }
        .stages { margin-top: 10px; }
        .stage-box { display: inline-block; border: 1px solid #999; padding: 3px 7px; margin: 2px 2px 2px 0; font-size: 10px; }
        .stage-box.current { background: #111; color: #fff; }
        .tick { font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="number">{{ $order->number }}</div>
            <div><strong>{{ $order->customer->name }}</strong></div>
            <div class="muted">{{ ucfirst($order->type->value) }} · Qty {{ $order->quantity }} · Due {{ $order->due_date?->format('d/m/Y') }}</div>
        </div>
        <img class="qr" src="data:image/png;base64,{{ $qrPng }}" alt="QR">
    </div>

    @if ($order->customer_notes)
        <div><strong>Brief:</strong> {{ $order->customer_notes }}</div>
    @endif

    <table>
        <tr>
            <th>Stage sign-off</th>
            <th>Initials</th>
            <th>Date</th>
        </tr>
        @foreach (\App\Models\ProductionStage::orderBy('position')->get() as $stage)
            <tr>
                <td>{{ $stage->name }} @if($stage->key === $order->stage->value) <span class="muted">(current)</span> @endif</td>
                <td style="width:25%"></td>
                <td style="width:25%"></td>
            </tr>
        @endforeach
    </table>

    @if ($order->allocations->count())
        <table>
            <tr><th>Stock</th><th>Allocated</th><th>Issued</th></tr>
            @foreach ($order->allocations as $alloc)
                <tr>
                    <td>{{ $alloc->stockItem->name }} ({{ $alloc->stockItem->sku }})</td>
                    <td>{{ $alloc->qty_allocated }}</td>
                    <td>{{ $alloc->qty_issued }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="muted" style="margin-top:8px">Printed {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
