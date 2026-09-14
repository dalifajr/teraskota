<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'transaction_time',
        'total_quantity',
        'total_sales',
        'total_profit',
        'estimated_cost',
        'payment_method',
        'cash_tendered',
        'change_returned',
        'customer_name',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_quantity' => 'integer',
        'total_sales' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'change_returned' => 'decimal:2',
    ];

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
