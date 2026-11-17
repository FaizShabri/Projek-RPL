<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Hanya menggunakan created_at di skema DB

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'before_state',
        'after_state',
        'created_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Mencegah mutasi atau penghapusan record audit log (Immutable).
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });

        static::updating(function ($model) {
            throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function ($model) {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Helper statis untuk mencatat audit log dengan mudah.
     */
    public static function record(
        ?int $actorId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $beforeState = null,
        ?array $afterState = null
    ): self {
        return self::create([
            'actor_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'created_at' => now(),
        ]);
    }
}
