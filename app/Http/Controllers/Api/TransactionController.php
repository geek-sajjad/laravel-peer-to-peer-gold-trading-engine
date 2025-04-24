<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        $transactions = Transaction::where('buyer_id', $userId)
            ->orWhere('seller_id', $userId)
            ->select([
                'id',
                'buy_order_id',
                'sell_order_id',
                'quantity',
                'price',
                'buyer_fee_gold',
                'seller_fee_irr',
                'status',
                'created_at',
                'updated_at'
            ])
            ->get()
            ->map(function ($transaction) use ($userId) {
                $orderId = null;
                $fee = null;

                if ($transaction->buyOrder && $transaction->buyOrder->user_id == $userId) {
                    $orderId = $transaction->buy_order_id;
                    $fee = $transaction->buyer_fee_gold;
                } elseif ($transaction->sellOrder && $transaction->sellOrder->user_id == $userId) {
                    $orderId = $transaction->sell_order_id;
                    $fee = $transaction->seller_fee_irr;
                }

                return [
                    'id' => $transaction->id,
                    'order_id' => $orderId,
                    'quantity' => $transaction->quantity,
                    'price' => $transaction->price,
                    'fee' => $fee,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,

                ];
            });

        return response()->json($transactions);
    }
}
