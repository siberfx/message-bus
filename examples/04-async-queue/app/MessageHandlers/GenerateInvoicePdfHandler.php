<?php

namespace App\MessageHandlers;

use App\Messages\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Storage;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateInvoicePdfHandler
{
    public function __construct(private readonly PdfRenderer $pdf)
    {
    }

    /**
     * @return array{invoice_id: int, url: string}
     */
    public function __invoke(GenerateInvoicePdf $message): array
    {
        $invoice = Invoice::with('lines', 'customer')->findOrFail($message->invoiceId);

        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('s3')->put($path, $this->pdf->render('pdf.invoice', [
            'invoice' => $invoice,
        ], $message->locale));

        $invoice->update(['pdf_path' => $path]);

        // Broadcast as "result" thanks to BroadcastOn(withResult: true).
        return [
            'invoice_id' => $invoice->id,
            'url' => Storage::disk('s3')->temporaryUrl($path, now()->addHour()),
        ];
    }
}
