<?php

namespace App\Services;

use App\Models\Order;
use League\Csv\Writer;

class ExportService
{
    public function xeroInvoicesCsv($orders): string
    {
        $csv = Writer::createFromString();
        $csv->insertOne([
            '*ContactName', '*InvoiceNumber', 'Reference', '*InvoiceDate', 'DueDate', 'Total', 'InventoryItemCode',
            'Description', 'Quantity', 'UnitAmount', 'Discount', 'AccountCode', 'TaxRate',
        ]);

        foreach ($orders as $order) {
            foreach ($order->lines as $i => $line) {
                $csv->insertOne([
                    $order->customer->name,
                    $order->number,
                    $order->reference,
                    $order->due_date?->format('Y-m-d'),
                    $order->due_date?->format('Y-m-d'),
                    number_format($order->price / 100, 2, '.', ''),
                    $line->stockItem?->sku ?? 'SERVICE',
                    $line->description,
                    $line->quantity,
                    number_format($line->price / 100, 2, '.', ''),
                    '0',
                    '200',
                    '20% (UK)',
                ]);
            }
        }

        return $csv->toString();
    }
}
