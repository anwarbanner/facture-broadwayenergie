<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const METHODS = [
        'transfer' => 'Virement',
        'check' => 'Cheque',
        'cash' => 'Especes',
        'card' => 'Carte',
        'bill' => 'Effet',
        'other' => 'Autre',
    ];

    protected $fillable = [
        'contact_id',
        'direction',
        'payment_date',
        'amount',
        'method',
        'reference',
        'notes',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? (string) $this->method;
    }

    public function directionLabel(): string
    {
        return $this->direction === 'in' ? 'Encaissement' : 'Decaissement';
    }
}
