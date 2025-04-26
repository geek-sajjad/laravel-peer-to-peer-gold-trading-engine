<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderMatchingService2
{
    protected FeeCalculateService $feeCalculateService;

    public function __construct(FeeCalculateService $feeCalculateService)
    {
        $this->feeCalculateService = $feeCalculateService;
    }

    public function matchAllOrders()
    {
        Log::info("Matching orders service running");
        $buyOrders = Order::where('type', 'buy')
            ->where('remaining_quantity', '>', 0.0001)
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderByDesc('price')
            ->orderBy('created_at')
            ->get();

        $sellOrders = Order::where('type', 'sell')
            ->where('remaining_quantity', '>', 0.0001)
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price')
            ->orderBy('created_at')
            ->get();


        Log::info('buyOrders', ['count' => $buyOrders->count()]);
        Log::info('sellOrders', ['count' => $sellOrders->count()]);


        $matches = [];
        Log::info('allBuyOrders', $buyOrders->toArray());
        Log::info('allSellOrders', $sellOrders->toArray());

        foreach ($buyOrders as $buyOrder) {
            foreach ($sellOrders as $sellOrder) {
                if ($sellOrder->price > $buyOrder->price) {
                    break; // No more matching possible
                }


                if ($sellOrder->remaining_quantity <= 0) {
                    Log::info('$sellOrder->remaining_quantity is zero for buyOrderId: ' . $buyOrder->id . ' and for sellOrderId: ' . $sellOrder->id);
                    continue;
                }
                if ($sellOrder->price > $buyOrder->price) {
                    break;
                }


                if ($this->canMatch($buyOrder, $sellOrder)) {
                    DB::transaction(function () use ($buyOrder, $sellOrder) {
                        // Lock the specific orders and their users
                        $lockedBuyOrder = Order::where('id', $buyOrder->id)->lockForUpdate()->firstOrFail();
                        $lockedSellOrder = Order::where('id', $sellOrder->id)->lockForUpdate()->firstOrFail();
                        $lockedBuyUser = $lockedBuyOrder->user()->lockForUpdate()->firstOrFail();
                        $lockedSellUser = $lockedSellOrder->user()->lockForUpdate()->firstOrFail();
                        // Recheck conditions inside the transaction to avoid race conditions
                        if (!$this->canMatch($lockedBuyOrder, $lockedSellOrder)) {
                            return;
                        }

                        $matchQuantity = min($buyOrder->remaining_quantity, $sellOrder->remaining_quantity);

                        $matchPrice = $this->determineMatchPrice($buyOrder, $sellOrder);

                        $buyerFee = $this->feeCalculateService->calculateFee($buyOrder->quantity, $matchQuantity, $matchPrice);
                        $sellerFee = $this->feeCalculateService->calculateFee($sellOrder->quantity, $matchQuantity, $matchPrice);
                        Log::info('buyerFee: ' . $buyerFee['goldFee']);
                        Log::info('sellerFee: ' . $sellerFee['irrFee']);


                        $transaction = Transaction::create([
                            'buy_order_id' => $buyOrder->id,
                            'sell_order_id' => $sellOrder->id,
                            'buyer_id' => $buyOrder->user_id,
                            'seller_id' => $sellOrder->user_id,
                            'quantity' => $matchQuantity,
                            'price' => $matchPrice,
                            'buyer_fee_gold' => $buyerFee['goldFee'],
                            'seller_fee_irr' => $sellerFee['irrFee'],
                            'status' => 'pending',
                        ]);


                        // Update orders
                        $this->updateOrder($lockedBuyOrder, $matchQuantity);
                        $this->updateOrder($lockedSellOrder, $matchQuantity);

                        // Update user balances
                        $this->updateUserBalance($lockedBuyOrder, $matchQuantity, $matchPrice, $buyerFee);
                        $this->updateUserBalance($lockedSellOrder, $matchQuantity, $matchPrice, $sellerFee);

                        $transaction->update(['status' => 'completed']);


                    });
                    if ($buyOrder->remaining_quantity <= 0) {
                        break; // Buy order is fully filled
                    }
                }


            }
        }


    }

    private function canMatch(Order $buyOrder, Order $sellOrder): bool
    {


        return
            $buyOrder->status != 'filled' &&
            $sellOrder->status != 'filled' &&
            $buyOrder->status != 'cancelled' &&
            $sellOrder->status != 'cancelled' &&
            $buyOrder->remaining_quantity > 0 &&
            $sellOrder->remaining_quantity > 0 &&
            $buyOrder->price >= $sellOrder->price;
    }

    private function determineMatchPrice(Order $buyOrder, Order $sellOrder): float
    {
        return $buyOrder->created_at <= $sellOrder->created_at
            ? $buyOrder->price
            : $sellOrder->price;
    }

    private function updateOrder(Order $order, float $matchedQuantity): void
    {
        $order->remaining_quantity -= $matchedQuantity;
        $order->filled_quantity += $matchedQuantity;
        $order->status = $order->remaining_quantity > 0 ? 'partially_filled' : 'filled';
        $order->save();
    }

    private function updateUserBalance(Order $order, float $matchedQuantity, float $matchedPrice, array $fee): void
    {
        $userFrozenIrrBalance = $order->user->frozen_irr_balance;
        $userFrozenGoldBalance = $order->user->frozen_gold_balance;

        if ($order->type == 'sell' && $userFrozenGoldBalance < $matchedQuantity) {
            throw new \Exception("Insufficient gold balance");
        }
        if ($order->type == 'buy' && $userFrozenIrrBalance < $matchedQuantity * $matchedPrice) {
            throw new \Exception("Insufficient IRR balance");
        }
        if ($order->type == 'sell') {
            $order->user->frozen_gold_balance -= $matchedQuantity;
            $order->user->available_irr_balance += ($matchedQuantity * $matchedPrice) - $fee['irrFee'];

        }

        if ($order->type == 'buy') {
            $order->user->frozen_irr_balance -= $matchedQuantity * $matchedPrice;
            $order->user->available_gold_balance += $matchedQuantity - $fee['goldFee'];

        }

        $order->user->save();
    }


}
