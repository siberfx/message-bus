<?php

namespace App\Http\Controllers;

use App\Messages\Queries\GetOrderSummary;
use Illuminate\View\View;
use Siberfx\MessageBus\Facades\MessageBus;

class OrderSummaryController extends Controller
{
    public function __invoke(int $order): View
    {
        return view('orders.summary', [
            'summary' => MessageBus::ask(new GetOrderSummary($order)),
        ]);
    }
}
