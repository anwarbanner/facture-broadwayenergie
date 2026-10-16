<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Products;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ERPDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hardware = Category::firstOrCreate(['name' => 'Hardware'], ['is_active' => true]);
        $services = Category::firstOrCreate(['name' => 'Services'], ['is_active' => true]);

        Warehouse::firstOrCreate(['name' => 'Main Warehouse'], [
            'location' => 'HQ',
            'manager' => 'Stock Manager',
        ]);

        Products::firstOrCreate(
            ['reference' => 'PRD-0001'],
            [
                'product_name' => 'Laptop Pro 15',
                'designation' => 'Laptop Pro 15',
                'description' => 'Demo stocked product',
                'type' => 'product',
                'category_id' => $hardware->id,
                'sale_price_ht' => 12500,
                'cost_price_ht' => 9800,
                'unit_type' => 'piece',
                'stock_quantity' => 20,
                'min_stock_alert_level' => 5,
                'status' => 'active',
            ]
        );

        Products::firstOrCreate(
            ['reference' => 'SRV-0001'],
            [
                'product_name' => 'Maintenance Contract',
                'designation' => 'Maintenance Contract',
                'description' => 'Demo service item',
                'type' => 'service',
                'category_id' => $services->id,
                'sale_price_ht' => 3200,
                'cost_price_ht' => 1800,
                'unit_type' => 'piece',
                'stock_quantity' => 0,
                'min_stock_alert_level' => 0,
                'status' => 'active',
            ]
        );

        Contact::firstOrCreate(
            ['company_name' => 'Atlas Client SARL', 'type' => 'client'],
            [
                'contact_person_name' => 'Client Manager',
                'email' => 'client@example.com',
                'phone' => '+212600000001',
                'city' => 'Casablanca',
                'country' => 'Morocco',
                'tax_id' => 'ICE-CLIENT-0001',
                'payment_terms' => '30 days',
                'credit_limit' => 50000,
                'status' => 'active',
            ]
        );

        Contact::firstOrCreate(
            ['company_name' => 'Rabat Supplier SA', 'type' => 'supplier'],
            [
                'contact_person_name' => 'Supplier Manager',
                'email' => 'supplier@example.com',
                'phone' => '+212600000002',
                'city' => 'Rabat',
                'country' => 'Morocco',
                'tax_id' => 'ICE-SUP-0001',
                'payment_terms' => '45 days',
                'credit_limit' => 0,
                'status' => 'active',
            ]
        );
    }
}
