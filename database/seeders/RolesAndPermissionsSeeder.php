<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Limpa cache de permissões (importante)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Cria permissões
        Permission::create(['name' => 'edit messages']);
        Permission::create(['name' => 'delete messages']);
        Permission::create(['name' => 'create rooms']);

        // Cria papéis e atribui permissões
        $roleAdmin = Role::create(['name' => 'admin']);
        $roleAdmin->givePermissionTo(Permission::all());

        $roleUser = Role::create(['name' => 'user']);
        $roleUser->givePermissionTo('create rooms');  // Exemplo de permissão básica para usuário comum
    }
}
