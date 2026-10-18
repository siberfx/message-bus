<?php

namespace App\Http\Controllers;

use App\Messages\GenerateInvoicePdf;
use App\Messages\SendNewsletter;
use App\Models\Campaign;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Stamp\TransportNamesStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;

class InvoiceController extends Controller
{
    public function generate(Invoice $invoice): JsonResponse
    {
        $envelope = MessageBus::dispatch(new GenerateInvoicePdf($invoice->id, $invoice->customer_id, app()->getLocale()));

        return response()->json([
            // The same id is present in the "invoice.ready" broadcast payload.
            'id' => $envelope->last(UniqueIdStamp::class)->id,
            'queued_on' => $envelope->last(SentStamp::class)?->senderAlias,
        ], 202);
    }

    /**
     * Small invoices are rendered right away: override the routing for this dispatch.
     */
    public function generateNow(Invoice $invoice): JsonResponse
    {
        $envelope = MessageBus::dispatch(
            new GenerateInvoicePdf($invoice->id, $invoice->customer_id),
            [new TransportNamesStamp('sync')],
        );

        return response()->json($envelope->last(\Siberfx\MessageBus\Stamp\HandledStamp::class)->result);
    }

    /**
     * Spread a campaign over time with delays.
     */
    public function sendCampaign(Campaign $campaign): JsonResponse
    {
        foreach ($campaign->subscribers()->cursor() as $index => $subscriber) {
            MessageBus::dispatch(new SendNewsletter($campaign->id, $subscriber->id), [
                DelayStamp::seconds(intdiv($index, 100) * 60), // 100 mails per minute
            ]);
        }

        // DelayStamp::delayUntil(now()->tomorrow()->setHour(9)) schedules for a moment in time.

        return response()->json(['queued' => true], 202);
    }
}
