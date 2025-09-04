<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'user.create',
            'user.view',
            'user.edit',
            'user.delete',
            
            // Product permissions
            'product.create',
            'product.view',
            'product.edit',
            'product.delete',
            
            // Order permissions
            'order.create',
            'order.view',
            'order.edit',
            'order.cancel',
            
            // Category permissions
            'category.manage'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $admin = Role::create(['name' => 'admin']);
        $seller = Role::create(['name' => 'seller']);
        $buyer = Role::create(['name' => 'buyer']);

        // Assign permissions
        $admin->givePermissionTo(Permission::all());
        
        $seller->givePermissionTo([
            'product.create',
            'product.view',
            'product.edit',
            'product.delete',
            'order.view'
        ]);
        
        $buyer->givePermissionTo([
            'order.create',
            'order.view',
            'order.cancel',
            'product.view'
        ]);
    }
}