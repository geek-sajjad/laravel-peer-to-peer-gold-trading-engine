<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'buy_order_id',
        'sell_order_id',
        'quantity',
        'price',
        'buyer_id',
        'seller_id',
        'status',
        'buyer_fee_gold',
        'seller_fee_irr',

    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'buyer_fee_gold' => 'decimal:3',
        'seller_fee_irr' => 'decimal:2',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'status' => 'string',
    ];


    protected $enums = [
        'status' => ['completed', 'pending', 'cancelled'],
    ];
    public function buyOrder()
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    public function sellOrder()
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
