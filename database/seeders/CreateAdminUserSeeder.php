<?php
namespace Database\Seeders; 
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
class CreateAdminUserSeeder extends Seeder
{
/**
* Run the database seeds.
*
* @return void
*/
public function run(){
    $superAdminRole = Role::firstOrCreate(['name' => 'Admin'], ['guard_name' => 'web']);
    $writerRole = Role::firstOrCreate(['name' => 'Technicien'], ['guard_name' => 'web']);

    $superAdmin = User::firstOrCreate(
        // Generic first accounts: change the e-mails and passwords right after installation.
        ['email' => 'admin@example.com'],
        [
            'name' => 'Administrateur',
            'password' => bcrypt('password'),
            'role_name' => ['Admin'],
            'status' => 'active',
        ]
    );

    $superAdminRole->syncPermissions(Permission::all());
    $superAdmin->syncRoles([$superAdminRole->name]);
    $superAdmin->update(['role_name' => [$superAdminRole->name], 'status' => 'active']);

    $writer = User::firstOrCreate(
        ['email' => 'technicien@example.com'],
        [
            'name' => 'Technicien',
            'password' => bcrypt('password'),
            'role_name' => ['Technicien'],
            'status' => 'active',
        ]
    );

    $writerRole->syncPermissions([
        'invoices_list',
        'paid_invoices_list',
        'unpaid_invoices_list',
        'partial_paid_invoices_list',
    ]);
    $writer->syncRoles([$writerRole->name]);
    $writer->update(['role_name' => [$writerRole->name], 'status' => 'active']);
    }
}