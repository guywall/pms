<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StockItem;
use App\Services\QrService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    public function jobTicket(Order $order, QrService $qr): Response
    {
        $url = url("/scan?code={$order->number}");
        $qrPng = base64_encode($qr->makePng($url, 160));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.job-ticket', [
            'order' => $order,
            'qrPng' => $qrPng,
        ])->setPaper('a6', 'portrait');

        return $pdf->stream("job-ticket-{$order->number}.pdf");
    }

    public function stockLabel(StockItem $stockItem, QrService $qr): Response
    {
        $qrPng = base64_encode($qr->makePng("SKU:{$stockItem->sku}", 160));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.stock-label', [
            'item' => $stockItem,
            'qrPng' => $qrPng,
        ])->setPaper([0, 0, 226.77, 226.77]); // ~8cm square label

        return $pdf->stream("label-{$stockItem->sku}.pdf");
    }
}
