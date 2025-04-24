<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\FeeCalculateService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class OrderController extends Controller
{

    protected FeeCalculateService $feeCalculateService;
    protected OrderService $orderService;

    public function __construct(FeeCalculateService $feeCalculateService, OrderService $orderService)
    {
        $this->feeCalculateService = $feeCalculateService;
        $this->orderService = $orderService;
    }

//    public function store(Request $request)
//    {
//        $request->validate([
//            'type' => 'required|in:buy,sell',
//            'quantity_gram' => 'required|numeric|min:0.001',
//            'price' => 'numeric|min:0.01',
//        ]);
//
//        $user = Auth::user();
//
//        $goldPrice=$request->get('price');
//
//        if(!$goldPrice) {
//            $goldPrice = GoldPrice::latest('timestamp')->first();
//        }
//
//
//        if (!$goldPrice) {
//            return response()->json(['error' => 'Gold price not available'], 400);
//        }
//
//
//        // Calculate the price based on the latest price and the quantity
//        $price = $goldPrice->price_irr;
//        $quantity = ((float)$request->get('quantity_gram'));
//
//
//        $minBuySellAmountGold = round(FeeCalculateService::MIN_FEE_IRR / $price, 3);
//        if ($user->null) {
//            throw new \Exception('User cannot be null');
//        }
//
//
//        $userIrrBalance = $user->available_irr_balance;
//        $userGoldBalance = $user->available_gold_balance;
//
//
//        if ($request->get('type') === 'buy' && $quantity <= $minBuySellAmountGold) {
//            return response()->json(['error' => 'buy amount gram must be larger than: ' . $minBuySellAmountGold], 400);
//        }
//
//        if ($request->get('type') === 'sell' && $quantity <= $minBuySellAmountGold) {
//            return response()->json(['error' => 'sell amount must gram be larger than: ' . $minBuySellAmountGold], 400);
//        }
////
//        // Validate balance
//        if ($request->get('type') === 'buy' && $userIrrBalance < $price * $quantity) {
//            return response()->json(['error' => 'Insufficient IRR balance'], 400);
//        }
//
//
//        if ($request->get('type') === 'sell' && $userGoldBalance < $quantity) {
//            return response()->json(['error' => 'Insufficient Gold balance'], 400);
//        }
//
//        $oppositeType = 'buy';
//        if ($request->get('type') === 'buy') {
//            $oppositeType = 'sell';
//        }
//
//        $order = $user->orders()
//            ->where('type', $oppositeType)
//            ->whereIn('status', ['open', 'partially_filled'])
//            ->get();
////        dump($order);
//        if (!$order->isEmpty()) {
//            return response()->json(['error' => 'You must have cancell all previous ' . $oppositeType . ' orders'], 400);
//        }
//
//        if ($request->get('type') === 'sell') {
//            $user->available_gold_balance -= $quantity;
//            $user->frozen_gold_balance += $quantity;
//
//            $user->save();
//        }
//
//        if ($request->get('type') === 'buy') {
//            $user->available_irr_balance -= $price * $quantity;
//            $user->frozen_irr_balance += $price * $quantity;
//
//            $user->save();
//        }
//
//
//        $order = new Order($request->only(['type']));
//        $order->user_id = $user->id;
//        $order->status = 'open';
//        $order->quantity = $quantity;
//        $order->remaining_quantity = $quantity;
//        $order->filled_quantity = 0;
//        $order->price = $price;  // Set the dynamic price
//
//        $order->save();
//
//        // Fire the event
//        event(new OrderPlaced($order));
//
//        return response()->json($order, 201);
//    }

    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'type' => 'required|in:buy,sell',
            'quantity_gram' => 'required|numeric|min:0.001',
            'price' => 'nullable|numeric|min:1000000|max:9999999999',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Get validated data
        $validated = $validator->validated();


        $user = Auth::user();

        try {
            $order = $this->orderService->createOrder($user, $validated);
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function index(Request $request)
    {
        $query = Auth::user()->orders();
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(10));
    }

    public function show(Order $order)
    {
        if (Auth::id() !== $order->user_id) {
            return response()->json(['error' => 'Unauthorized access to this order'], 403);
        }

        try {
            $response = $this->orderService->getOrderDetails($order);
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

//    public function show(Order $order)
//    {
//        if (Auth::id() !== $order->user_id) {
//            return response()->json(['error' => 'Unauthorized access to this order'], 403);
//        }
//
//        $order->load([
//            'buyTransactions' => fn($query) => $query->select([
//                'id',
//                'buy_order_id',
//                'sell_order_id',
//                'quantity',
//                'price',
//                'status',
//                'buyer_fee_gold',
//                'seller_fee_irr',
//                'created_at',
//            ]),
//            'sellTransactions' => fn($query) => $query->select([
//                'id',
//                'buy_order_id',
//                'sell_order_id',
//                'quantity',
//                'price',
//                'status',
//                'buyer_fee_gold',
//                'seller_fee_irr',
//                'created_at',
//            ]),
//        ]);
//
//        return response()->json([
//            'order' => [
//                'id' => $order->id,
//                'type' => $order->type,
//                'quantity' => $order->quantity,
//                'remaining_quantity' => $order->remaining_quantity,
//                'filled_quantity' => $order->filled_quantity,
//                'price' => $order->price,
//                'status' => $order->status,
//                'created_at' => $order->created_at->toIso8601String(),
//            ],
//            'transactions' => $order->buyTransactions->merge($order->sellTransactions)->map(function ($transaction) use ($order) {
//                $fee = $transaction->buy_order_id == $order->id
//                    ? $transaction->buyer_fee_gold // Buy order: show buyer fee
//                    : $transaction->seller_fee_irr; // Sell order: show seller fee
//
//                return [
//                    'id' => $transaction->id,
//                    'quantity' => $transaction->quantity,
//                    'price' => $transaction->price,
//                    'status' => $transaction->status,
//                    'fee' => $fee,
//                    'created_at' => $transaction->created_at->toIso8601String(),
//                    'type' => $transaction->buy_order_id == $order->id ? 'buy' : 'sell',
//                ];
//            })->sortByDesc('created_at')->values(),
//        ]);
//    }

//    public function cancel(Order $order)
//    {
//        // Check if the user owns the order
//        if (Auth::id() !== $order->user_id) {
//            return response()->json(['error' => 'Unauthorized access to this order'], 403);
//        }
//
//        // Check if the order is in a cancellable state
//        if (!in_array($order->status, ['open', 'partially_filled'])) {
//            return response()->json(['error' => 'Order cannot be canceled. It is already ' . $order->status], 422);
//        }
//
//        try {
//            // Use a transaction with a lock to ensure no worker job is processing
//            DB::transaction(function () use ($order) {
//                // Acquire a lock on the order
//                $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
//                $order->user->lockForUpdate()->firstOrFail();
//                $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();
//
//                // Double-check status inside the transaction
//                if (!in_array($order->status, ['open', 'partially_filled'])) {
//                    throw new \Exception('Order cannot be canceled. It is already ' . $order->status);
//                }
//                $remainingQuantity = $order->remaining_quantity;
//
//                // Cancel the order
//                $order->update([
//                    'status' => 'cancelled',
//                    'remaining_quantity' => 0,
//                    'updated_at' => now(),
//                ]);
//
//                if ($order->type == 'buy') {
//                    $user->frozen_irr_balance -= $remainingQuantity * $order->price;
//                    $user->available_irr_balance += $remainingQuantity * $order->price;
//                }
//
//                if ($order->type == 'sell') {
//                    $user->frozen_gold_balance -= $remainingQuantity;
//                    $user->available_gold_balance += $remainingQuantity;
//                }
//
//                $user->save();
//            });
//
//            return response()->json([
//                'message' => 'Order canceled successfully',
//                'order' => [
//                    'id' => $order->id,
//                    'status' => 'cancelled',
//                ],
//            ]);
//        } catch (\Exception $e) {
//            return response()->json(['error' => $e->getMessage()], 422);
//        }
//    }

    public function cancel(Order $order)
    {
        if (Auth::id() !== $order->user_id) {
            return response()->json(['error' => 'Unauthorized access to this order'], 403);
        }

        try {
            $this->orderService->cancelOrder($order);
            return response()->json([
                'message' => 'Order canceled successfully',
                'order' => [
                    'id' => $order->id,
                    'status' => 'cancelled',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

}
