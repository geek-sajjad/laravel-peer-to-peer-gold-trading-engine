<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'quantity',
        'remaining_quantity',
        'filled_quantity',
        'price',
        'status',
    ];

    protected $casts = [
        'type' => 'string',
        'quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'filled_quantity' => 'decimal:3',
        'price' => 'decimal:2',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $enums = [
        'type' => ['buy', 'sell'],
        'status' => ['open', 'partially_filled', 'filled', 'cancelled'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function buyTransactions()
    {
        return $this->hasMany(Transaction::class, 'buy_order_id');
    }

    public function sellTransactions()
    {
        return $this->hasMany(Transaction::class, 'sell_order_id');
    }

    public function transactions()
    {
        return Transaction::where('buy_order_id', $this->id)
            ->orWhere('sell_order_id', $this->id);
    }
}
