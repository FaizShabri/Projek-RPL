<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Enums\UserRole;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        // - Platform Level
        $manageSettings = Permission::firstOrCreate(['name' => 'manage_settings']);
        $manageFinances = Permission::firstOrCreate(['name' => 'manage_finances']);
        $manageTokosPlatform = Permission::firstOrCreate(['name' => 'manage_tokos_platform']); // Approve/Reject toko
        $accountsApprove = Permission::firstOrCreate(['name' => 'accounts.approve']);
        $accountsManage = Permission::firstOrCreate(['name' => 'accounts.manage']);
        
        // - Toko Level
        $manageOwnToko = Permission::firstOrCreate(['name' => 'manage_own_toko']);
        $manageTokoOrders = Permission::firstOrCreate(['name' => 'manage_toko_orders']);
        
        // - Kurir Level
        $acceptOrders = Permission::firstOrCreate(['name' => 'accept_orders']);
        
        // - Customer Level
        $createOrders = Permission::firstOrCreate(['name' => 'create_orders']);
        
        // 2. Define Roles and Assign Permissions
        
        // A. Platform Owner (Highest Level - all access)
        $rolePlatformOwner = Role::firstOrCreate(['name' => UserRole::PLATFORM_OWNER->value]);
        $rolePlatformOwner->givePermissionTo(Permission::all());
        
        // B. Admin Owner (Platform admin, NO core finances access)
        $roleAdminOwner = Role::firstOrCreate(['name' => UserRole::ADMIN_OWNER->value]);
        $roleAdminOwner->givePermissionTo([
            $manageSettings,
            $manageTokosPlatform,
        ]);
        
        // C. Owner Toko (Mitra)
        $roleOwnerToko = Role::firstOrCreate(['name' => UserRole::OWNER_TOKO->value]);
        $roleOwnerToko->givePermissionTo([
            $manageOwnToko,
            $manageTokoOrders,
        ]);
        
        // D. Admin Toko (Sub-admin toko, scoped by staff_tokos pivot table in application level)
        $roleAdminToko = Role::firstOrCreate(['name' => UserRole::ADMIN_TOKO->value]);
        $roleAdminToko->givePermissionTo([
            $manageTokoOrders,
        ]);
        
        // E. Kurir
        $roleKurir = Role::firstOrCreate(['name' => UserRole::KURIR->value]);
        $roleKurir->givePermissionTo([
            $acceptOrders,
        ]);
        
        // F. Customer
        $roleCustomer = Role::firstOrCreate(['name' => UserRole::CUSTOMER->value]);
        $roleCustomer->givePermissionTo([
            $createOrders,
        ]);
    }
}
