<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PlatformAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'category',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'description',
        'ip_address',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(
        string $category,
        string $action,
        ?string $targetType,
        ?int $targetId,
        ?string $targetLabel,
        string $description
    ): ?self {
        try {
            return static::create([
                'admin_id' => Auth::id(),
                'category' => $category,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_label' => $targetLabel,
                'description' => $description,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
