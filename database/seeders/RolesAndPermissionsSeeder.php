<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder {
    public function run() {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define o guard padrão para este seeder
        $guard = 'api'; // <-- GARANTE QUE É 'api'

        // --- Permissões ---
        $permissions = [
            'view', // <- Adicionei 'view' se for necessária como permissão explícita
            'send-messages',
            'create-rooms',
            'leave-room',
            'delete-any-message',
            'edit-any-message',
            'delete-any-room',
            'add-member-room',
            // Adicione outras permissões se necessário (ex: 'edit-messages', 'delete-messages')
            'edit-messages',
            'delete-messages',
        ];

        // Cria permissões COM O GUARD 'api'
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]); // <-- Adiciona guard_name
        }

        // --- Define quais permissões cada papel (exceto master) terá ---
        $permUser = ['view', 'send-messages', 'create-rooms', 'leave-room', 'edit-messages', 'delete-messages']; // Usuário pode editar/deletar a *própria* msg
        $permManager = ['delete-any-message']; // Manager pode deletar qqr msg
        $permAdmin = ['edit-any-message', 'delete-any-room', 'add-member-room']; // Admin pode mais

        // --- Cria Papéis COM O GUARD 'api' e Atribui Permissões ---
        Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard]) // <-- Adiciona guard_name
        ->syncPermissions(Permission::whereIn('name', $permUser)->where('guard_name', $guard)->get());

        Role::firstOrCreate(['name' => 'manager', 'guard_name' => $guard]) // <-- Adiciona guard_name
        ->syncPermissions(Permission::whereIn('name', array_merge($permUser, $permManager))->where('guard_name', $guard)->get());

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]) // <-- Adiciona guard_name
        ->syncPermissions(Permission::whereIn('name', array_merge($permUser, $permManager, $permAdmin))->where('guard_name', $guard)->get());

        Role::firstOrCreate(['name' => 'master', 'guard_name' => $guard]) // <-- Adiciona guard_name
        ->syncPermissions(Permission::where('guard_name', $guard)->get()); // Pega todas do guard 'api'
    }
}
