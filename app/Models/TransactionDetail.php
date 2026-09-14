<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    protected $fillable = [
        'transaction_id',
        'menu_id',
        'menu_name_snapshot',
        'category_name_snapshot',
        'price_snapshot',
        'profit_percentage_snapshot',
        'quantity',
        'subtotal',
        'profit_amount',
        'estimated_cost',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
        'profit_percentage_snapshot' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'profit_amount' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
