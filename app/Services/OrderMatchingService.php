<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use RuntimeException;

class OrderMatchingService
{
    protected FeeCalculateService $feeCalculateService;

    public function __construct(FeeCalculateService $feeCalculateService)
    {
        $this->feeCalculateService = $feeCalculateService;
    }

    public function matchAllOrders()
    {
        try {
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

            foreach ($buyOrders as $buyOrder) {
                try {
                    foreach ($sellOrders as $sellOrder) {

                        if ($sellOrder->price > $buyOrder->price) {
                            break; // No more matching possible
                        }

                        if ($sellOrder->remaining_quantity <= 0) {
                            Log::info('$sellOrder->remaining_quantity is zero for buyOrderId: ' . $buyOrder->id . ' and for sellOrderId: ' . $sellOrder->id);
                            continue;
                        }

                        if ($this->canMatch($buyOrder, $sellOrder)) {
                            $this->processOrderMatch($buyOrder, $sellOrder);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error processing buy order', [
                        'buyOrderId' => $buyOrder->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    continue; // Continue with next buy order
                }
            }
        } catch (Exception $e) {
            Log::error('Fatal error in order matching service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to match orders: ' . $e->getMessage());
        }
    }

    private function processOrderMatch(Order $buyOrder, Order $sellOrder)
    {
        DB::transaction(function () use ($buyOrder, $sellOrder) {
            try {
                // Lock the specific orders and their users
                $lockedBuyOrder = Order::where('id', $buyOrder->id)->lockForUpdate()->firstOrFail();
                $lockedSellOrder = Order::where('id', $sellOrder->id)->lockForUpdate()->firstOrFail();
                $lockedBuyUser = $lockedBuyOrder->user()->lockForUpdate()->firstOrFail();
                $lockedSellUser = $lockedSellOrder->user()->lockForUpdate()->firstOrFail();

                // Recheck conditions inside the transaction
                if (!$this->canMatch($lockedBuyOrder, $lockedSellOrder)) {
                    Log::warning('Order match conditions no longer valid', [
                        'buyOrderId' => $lockedBuyOrder->id,
                        'sellOrderId' => $lockedSellOrder->id
                    ]);
                    return;
                }

                $matchQuantity = min($buyOrder->remaining_quantity, $sellOrder->remaining_quantity);
                $matchPrice = $this->determineMatchPrice($buyOrder, $sellOrder);

                // Calculate fees
                $buyerFee = $this->feeCalculateService->calculateFee($buyOrder->quantity, $matchQuantity, $matchPrice);
                $sellerFee = $this->feeCalculateService->calculateFee($sellOrder->quantity, $matchQuantity, $matchPrice);

                Log::info('buyerFee: ' . $buyerFee['goldFee']);
                Log::info('sellerFee: ' . $sellerFee['irrFee']);

                // Create transaction
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

                // Update orders and balances
                $this->updateOrder($lockedBuyOrder, $matchQuantity);
                $this->updateOrder($lockedSellOrder, $matchQuantity);
                $this->updateUserBalance($lockedBuyOrder, $matchQuantity, $matchPrice, $buyerFee);
                $this->updateUserBalance($lockedSellOrder, $matchQuantity, $matchPrice, $sellerFee);

                $transaction->update(['status' => 'completed']);

                Log::info('Order match completed successfully', [
                    'buyOrderId' => $buyOrder->id,
                    'sellOrderId' => $sellOrder->id,
                    'quantity' => $matchQuantity,
                    'price' => $matchPrice
                ]);

            } catch (Exception $e) {
                Log::error('Error processing order match', [
                    'buyOrderId' => $buyOrder->id,
                    'sellOrderId' => $sellOrder->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new RuntimeException('Failed to process order match: ' . $e->getMessage());
            }
        });
    }

    private function canMatch(Order $buyOrder, Order $sellOrder): bool
    {
        try {
            return
                $buyOrder->status != 'filled' &&
                $sellOrder->status != 'filled' &&
                $buyOrder->status != 'cancelled' &&
                $sellOrder->status != 'cancelled' &&
                $buyOrder->remaining_quantity > 0 &&
                $sellOrder->remaining_quantity > 0 &&
                $buyOrder->price >= $sellOrder->price;
        } catch (Exception $e) {
            Log::error('Error checking order match conditions', [
                'buyOrderId' => $buyOrder->id,
                'sellOrderId' => $sellOrder->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function determineMatchPrice(Order $buyOrder, Order $sellOrder): float
    {
        try {
            return $buyOrder->created_at <= $sellOrder->created_at
                ? $buyOrder->price
                : $sellOrder->price;
        } catch (Exception $e) {
            Log::error('Error determining match price', [
                'buyOrderId' => $buyOrder->id,
                'sellOrderId' => $sellOrder->id,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to determine match price: ' . $e->getMessage());
        }
    }

    private function updateOrder(Order $order, float $matchedQuantity): void
    {
        try {
            $order->remaining_quantity -= $matchedQuantity;
            $order->filled_quantity += $matchedQuantity;
            $order->status = $order->remaining_quantity > 0 ? 'partially_filled' : 'filled';
            $order->save();
        } catch (Exception $e) {
            Log::error('Error updating order', [
                'orderId' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to update order: ' . $e->getMessage());
        }
    }

    private function updateUserBalance(Order $order, float $matchedQuantity, float $matchedPrice, array $fee): void
    {
        try {
            $userFrozenIrrBalance = $order->user->frozen_irr_balance;
            $userFrozenGoldBalance = $order->user->frozen_gold_balance;

            if ($order->type == 'sell' && $userFrozenGoldBalance < $matchedQuantity) {
                throw new RuntimeException("Insufficient gold balance for order {$order->id}");
            }
            if ($order->type == 'buy' && $userFrozenIrrBalance < $matchedQuantity * $matchedPrice) {
                throw new RuntimeException("Insufficient IRR balance for order {$order->id}");
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
        } catch (Exception $e) {
            Log::error('Error updating user balance', [
                'orderId' => $order->id,
                'userId' => $order->user_id,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to update user balance: ' . $e->getMessage());
        }
    }
}
