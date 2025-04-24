<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GoldPrice extends Model
{
    use HasFactory;

    // Specify the table if it does not follow Laravel's pluralization rule
    protected $table = 'gold_prices';

    // Specify the fillable attributes
    protected $fillable = [
        'price_irr',
        'timestamp',
    ];

    // Ensure the timestamps are handled correctly
    public $timestamps = true;

    // Cast the 'timestamp' to a Carbon instance for easy manipulation
    protected $casts = [
        'timestamp' => 'datetime',
        'price' => 'decimal:2',
    ];

}
