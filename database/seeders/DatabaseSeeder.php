<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    /**
     * Seed the application's database.
     */
    public function run(): void {
        // 1. Cria os Papéis e Permissões primeiro
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // 2. Busca o papel 'master' que acabou de ser criado
        $masterRole = Role::findByName('master');

        // 3. Cria seus usuários Master
        User::firstOrCreate(
            ['email' => 'matheuspurgato@gmail.com'],
            [
                'name' => 'Matheus Purgato', // Nome completo
                'password' => Hash::make('Purgato$123**'),
            ]
        )->assignRole($masterRole); // Atribui o papel 'master'

        User::firstOrCreate(
            ['email' => 'm.purgato@interacti.com.br'],
            [
                'name' => 'Matheus Purgato InterACTI', // Nome
                'password' => Hash::make('Purgato$123**'),
            ]
        )->assignRole($masterRole); // Atribui o papel 'master'
    }
}
