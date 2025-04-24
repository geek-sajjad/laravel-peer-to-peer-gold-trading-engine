<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\OrderMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;;
class StartOrderMatchingService implements ShouldQueue
{
    public $queue = 'order_matching';
    protected OrderMatchingService $matchingService;
    /**
     * Create the event listener.
     */
    public function __construct(OrderMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        Log::info('MatchOrderJob: Order placed on Handler', [
            'order_id' => $order->id,
            'order_type' => $order->type,
        ]);
        $this->matchingService->matchAllOrders();
    }
}
