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
