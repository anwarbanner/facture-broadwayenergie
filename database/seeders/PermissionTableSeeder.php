<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
/**
* Run the database seeds.
*
* @return void
*/
public function run()
{
    $permissions = [
        'invoices',
        'invoices_list',
        'paid_invoices_list',
        'unpaid_invoices_list',
        'partial_paid_invoices_list',
        'trashed_invoices',
        'تعديل مستخدم',
        'حذف مستخدم',
        'user_edit',
        'user_delete',
        'role_create',
        'role_view',
        'role_edit',
        'role_delete',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

}
}