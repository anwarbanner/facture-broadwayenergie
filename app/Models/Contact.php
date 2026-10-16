<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'company_name',
        'contact_person_name',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'postal_code',
        'country',
        'tax_id',
        'payment_terms',
        'credit_limit',
        'status',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'tags' => 'array',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class);
    }

    public function salesQuotes(): HasMany
    {
        return $this->hasMany(SalesQuote::class, 'client_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'client_id');
    }

    public function clientInvoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class, 'client_id');
    }

    public function purchaseQuotes(): HasMany
    {
        return $this->hasMany(PurchaseQuote::class, 'supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'supplier_id');
    }

    public function scopeClients($query)
    {
        return $query->where('type', 'client');
    }

    public function scopeSuppliers($query)
    {
        return $query->where('type', 'supplier');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
                ->orWhere('contact_person_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        });
    }
}
