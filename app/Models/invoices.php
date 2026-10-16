<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;


class invoices extends Model
{
    use HasFactory;
    use SoftDeletes;
    
    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'Due_date',
        'product',
        'Amount_collection',
        'Amount_Commission',
        'Discount',
        'Value_VAT',
        'Rate_VAT',
        'Total',
        'status',
        'Value_Status',
        'note',
        'Payment_Date',
    ];
    
}
