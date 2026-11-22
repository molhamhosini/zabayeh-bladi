<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'slaughtering', 'packing', 'out_for_delivery', 'delivered', 'cancelled', 'returned', 'failed_delivery'];

    public const PURPOSES = ['normal', 'udhiya', 'aqiqah', 'sadaqah'];

    /** Allowed forward moves; anything not listed is rejected. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['slaughtering', 'cancelled'],
        'slaughtering' => ['packing'],
        'packing' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered', 'failed_delivery'],
        'failed_delivery' => ['out_for_delivery', 'cancelled'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    protected $fillable = ['number', 'user_id', 'branch_id', 'address_id', 'delivery_slot_id', 'purpose', 'beneficiary_name',
        'donate_to_charity', 'status', 'payment_method', 'payment_status', 'stripe_payment_intent_id',
        'subtotal', 'delivery_fee', 'vat', 'total', 'notes', 'driver_id'];

    protected $casts = ['donate_to_charity' => 'boolean', 'subtotal' => 'decimal:2', 'delivery_fee' => 'decimal:2',
        'vat' => 'decimal:2', 'total' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class, 'delivery_slot_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('id');
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function moveTo(string $status, ?User $by = null, ?string $note = null): void
    {
        if (! $this->canMoveTo($status)) {
            throw new \DomainException("Cannot move order from {$this->status} to {$status}");
        }
        $this->update(['status' => $status]);
        $this->statusLogs()->create(['status' => $status, 'user_id' => $by?->id, 'note' => $note]);
    }
}
