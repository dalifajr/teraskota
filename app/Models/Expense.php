<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_number',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public const CATEGORIES = [
        'Bahan Baku & Es' => 'Bahan Baku & Es',
        'Kemasan & Cup' => 'Kemasan & Cup',
        'Operasional & Utilitas' => 'Operasional & Utilitas',
        'Gaji & Insentif' => 'Gaji & Insentif',
        'Perawatan & Alat' => 'Perawatan & Alat',
        'Lain-lain' => 'Lain-lain',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate unique expense number (EXP-YYYYMMDD-XXXX).
     */
    public static function generateExpenseNumber(string $date): string
    {
        $formattedDate = Carbon::parse($date)->format('Ymd');
        $prefix = "EXP-{$formattedDate}-";

        $latest = static::withTrashed()
            ->whereDate('expense_date', $date)
            ->where('expense_number', 'like', "{$prefix}%")
            ->orderBy('expense_number', 'desc')
            ->lockForUpdate()
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->expense_number);
            $counter = (int) end($parts);
            $nextCounter = str_pad($counter + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextCounter = '0001';
        }

        return $prefix . $nextCounter;
    }
}

