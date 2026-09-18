<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Label {{ $item->sku }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; margin: 6px; }
        .sku { font-size: 16px; font-weight: bold; }
        .name { font-size: 11px; }
        .meta { font-size: 10px; color: #444; }
        .qr { width: 100px; }
        .barcode { width: 100%; height: 40px; }
    </style>
</head>
<body>
    <table style="width:100%">
        <tr>
            <td>
                <div class="sku">{{ $item->sku }}</div>
                <div class="name">{{ $item->name }}</div>
                <div class="meta">{{ $item->unit }} · reorder at {{ $item->reorder_level }}</div>
            </td>
            <td style="width:110px; text-align:right">
                <img class="qr" src="data:image/png;base64,{{ $qrPng }}" alt="QR">
            </td>
        </tr>
    </table>
    <img class="barcode" src="@php
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        echo 'data:image/png;base64,'.base64_encode($generator->getBarcode($item->sku, $generator::TYPE_CODE_128));
    @endphp" alt="Barcode">
</body>
</html>
