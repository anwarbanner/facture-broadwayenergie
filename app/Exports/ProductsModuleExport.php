<?php

namespace App\Exports;

use App\Models\Products;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsModuleExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Products::query()->select([
            'reference',
            'product_name',
            'designation',
            'type',
            'sale_price_ht',
            'cost_price_ht',
            'stock_quantity',
            'min_stock_alert_level',
            'status',
        ])->get();
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Name',
            'Designation',
            'Type',
            'Sale HT',
            'Cost HT',
            'Stock qty',
            'Min stock',
            'Status',
        ];
    }
}
