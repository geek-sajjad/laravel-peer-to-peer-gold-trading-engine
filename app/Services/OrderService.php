<?php
namespace App\Services;

use App\Models\GoldPrice;
use App\Models\Order;
use App\Models\User;
use App\Events\OrderPlaced;
use Exception;

class OrderService
{
    public function createOrder(User $user, array $data)
    {
        $type = $data['type'];
        $quantity = (float) $data['quantity_gram'];
        $price = $this->getGoldPrice($data['price'] ?? null);
        $minQuantity = $this->calculateMinQuantity($price);

        if ($quantity <= $minQuantity) {
            throw new Exception("{$type} amount must be larger than: {$minQuantity} grams");
        }

        $this->checkUserBalance($user, $type, $quantity, $price);
        $this->checkExistingOrders($user, $type);
        $this->adjustUserBalance($user, $type, $quantity, $price);

        $order = $this->createOrderRecord($user, $type, $quantity, $price);
        event(new OrderPlaced($order));

        return $order;
    }

    public function getOrderDetails(Order $order)
    {
        $this->loadOrderTransactions($order);
        return $this->formatOrderResponse($order);
    }

    public function cancelOrder(Order $order)
    {
        $this->validateOrderStatus($order);

        DB::transaction(function () use ($order) {
            // Lock the order and user to prevent concurrent modifications
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();

            // Double-check status inside the transaction
            $this->validateOrderStatus($lockedOrder);

            // Update order status
            $remainingQuantity = $lockedOrder->remaining_quantity;
            $lockedOrder->update([
                'status' => 'cancelled',
                'remaining_quantity' => 0,
                'updated_at' => now(),
            ]);

            // Adjust user balances
            $this->adjustUserBalanceForCancellation($user, $lockedOrder->type, $remainingQuantity, $lockedOrder->price);
        });
    }

    private function getGoldPrice($providedPrice = null)
    {
        if ($providedPrice !== null) {
            return (float) $providedPrice;
        }

        $latestGoldPrice = GoldPrice::latest('timestamp')->first();
        if (!$latestGoldPrice) {
            throw new Exception('Gold price not available');
        }

        return $latestGoldPrice->price_irr;
    }

    private function calculateMinQuantity(float $price): float
    {
        if ($price <= 0) {
            throw new \Exception('Price must be greater than zero');
        }

        return round(FeeCalculateService::MIN_FEE_IRR / $price, 3);
    }
    private function checkUserBalance(User $user, $type, $quantity, $price)
    {
        if ($type === 'buy') {
            $requiredIrr = $price * $quantity;
            if ($user->available_irr_balance < $requiredIrr) {
                throw new Exception('Insufficient IRR balance');
            }
        } elseif ($type === 'sell') {
            if ($user->available_gold_balance < $quantity) {
                throw new Exception('Insufficient Gold balance');
            }
        }
    }

    private function checkExistingOrders(User $user, $type)
    {
        $oppositeType = $type === 'buy' ? 'sell' : 'buy';
        $existingOrders = $user->orders()
            ->where('type', $oppositeType)
            ->whereIn('status', ['open', 'partially_filled'])
            ->exists();

        if ($existingOrders) {
            throw new Exception("You must cancel all previous {$oppositeType} orders");
        }
    }

    private function adjustUserBalance(User $user, $type, $quantity, $price)
    {
        if ($type === 'buy') {
            $cost = $price * $quantity;
            $user->available_irr_balance -= $cost;
            $user->frozen_irr_balance += $cost;
        } elseif ($type === 'sell') {
            $user->available_gold_balance -= $quantity;
            $user->frozen_gold_balance += $quantity;
        }
        $user->save();
    }

    private function createOrderRecord(User $user, $type, $quantity, $price)
    {
        $order = new Order([
            'type' => $type,
            'user_id' => $user->id,
            'status' => 'open',
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'filled_quantity' => 0,
            'price' => $price,
        ]);
        $order->save();
        return $order;
    }

    private function validateOrderStatus(Order $order)
    {
        if (!in_array($order->status, ['open', 'partially_filled'])) {
            throw new Exception("Order cannot be canceled. It is already {$order->status}");
        }
    }

    private function adjustUserBalanceForCancellation(User $user, $type, $quantity, $price)
    {
        if ($type === 'buy') {
            $amount = $quantity * $price;
            $user->frozen_irr_balance -= $amount;
            $user->available_irr_balance += $amount;
        } elseif ($type === 'sell') {
            $user->frozen_gold_balance -= $quantity;
            $user->available_gold_balance += $quantity;
        }
        $user->save();
    }

    private function loadOrderTransactions(Order $order)
    {
        $order->load([
            'buyTransactions' => fn($query) => $query->select([
                'id',
                'buy_order_id',
                'sell_order_id',
                'quantity',
                'price',
                'status',
                'buyer_fee_gold',
                'seller_fee_irr',
                'created_at',
            ]),
            'sellTransactions' => fn($query) => $query->select([
                'id',
                'buy_order_id',
                'sell_order_id',
                'quantity',
                'price',
                'status',
                'buyer_fee_gold',
                'seller_fee_irr',
                'created_at',
            ]),
        ]);
    }

    private function formatOrderResponse(Order $order)
    {
        return [
            'order' => [
                'id' => $order->id,
                'type' => $order->type,
                'quantity' => $order->quantity,
                'remaining_quantity' => $order->remaining_quantity,
                'filled_quantity' => $order->filled_quantity,
                'price' => $order->price,
                'status' => $order->status,
                'created_at' => $order->created_at->toIso8601String(),
            ],
            'transactions' => $order->buyTransactions->merge($order->sellTransactions)
                ->map(function ($transaction) use ($order) {
                    $fee = $transaction->buy_order_id == $order->id
                        ? $transaction->buyer_fee_gold
                        : $transaction->seller_fee_irr;

                    return [
                        'id' => $transaction->id,
                        'quantity' => $transaction->quantity,
                        'price' => $transaction->price,
                        'status' => $transaction->status,
                        'fee' => $fee,
                        'created_at' => $transaction->created_at->toIso8601String(),
                        'type' => $transaction->buy_order_id == $order->id ? 'buy' : 'sell',
                    ];
                })
                ->sortByDesc('created_at')
                ->values(),
        ];
    }
}
