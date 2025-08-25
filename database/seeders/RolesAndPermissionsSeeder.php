<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            'manage-users',
            'manage-roles',
            'manage-system-settings',
            'manage-baremetrics',
            'manage-stripe',
            'manage-cancellations',
            'view-dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear rol Admin
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Crear usuario admin si no existe
        $admin = User::firstOrCreate(
            ['email' => 'admin@creetelo.club'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Admin');

        // Crear usuario admin si no existe
        $braulio = User::firstOrCreate(
            ['email' => 'braulio@felamedia.com'],
            [
                'name' => 'Braulio',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $braulio->assignRole('Admin');
    }
}
