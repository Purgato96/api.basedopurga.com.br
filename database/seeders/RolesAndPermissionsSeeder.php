<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder {
    public function run() {
        // Limpa cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Permissões de Usuário ---
        $permUser = [
            'send-messages',
            'create-rooms',
            'leave-room',
        ];

        // --- Permissões de Manager (Moderador) ---
        $permManager = [
            'delete-any-message',
        ];

        // --- Permissões de Admin ---
        $permAdmin = [
            'edit-any-message',
            'delete-any-room',
            'add-member-room',
        ];

        // Cria todas as permissões de uma vez
        foreach (array_merge($permUser, $permManager, $permAdmin) as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // --- Cria Papéis e Atribui Permissões ---

        // User: Permissões básicas
        Role::firstOrCreate(['name' => 'user'])
            ->syncPermissions($permUser);

        // Manager: Tudo que um User faz + permissões de Manager
        Role::firstOrCreate(['name' => 'manager'])
            ->syncPermissions(array_merge($permUser, $permManager));

        // Admin: Tudo que um Manager faz + permissões de Admin
        Role::firstOrCreate(['name' => 'admin'])
            ->syncPermissions(array_merge($permUser, $permManager, $permAdmin));

        // Master: Pode tudo
        Role::firstOrCreate(['name' => 'master'])
            ->syncPermissions(Permission::all());
    }
}
