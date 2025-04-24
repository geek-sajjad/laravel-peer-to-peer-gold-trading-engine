<?php

namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'balances' => [
                'available_gold_balance' => $user->available_gold_balance,
                'available_irr_balance' => $user->available_irr_balance,
                'frozen_gold_balance' => $user->frozen_gold_balance,
                'frozen_irr_balance' => $user->frozen_irr_balance,
            ]
        ]);
    }
}
