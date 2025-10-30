<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder {
    /**
     * Seed the application's database.
     */
    public function run(): void {
        // 1. Cria os Papéis e Permissões primeiro
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // 2. Busca o papel 'master' NO GUARD 'api'
        $guard = 'api'; // <-- Define o guard
        $masterRole = Role::findByName('master', $guard); // <-- PASSA O GUARD AQUI

        // 3. Verifica se o papel foi encontrado
        if (!$masterRole) {
            // Se não encontrou, loga um erro fatal. O seeder vai parar.
            Log::error("DatabaseSeeder: Erro Crítico - Papel 'master' (guard 'api') não encontrado.");
            $this->command->error("Erro Crítico: Papel 'master' (guard 'api') não encontrado. Seeders de usuário abortados.");
            return;
        }
        Log::info("DatabaseSeeder: Papel 'master' (guard 'api') encontrado. Criando usuários master...");

        // 4. Cria seus usuários Master
        User::firstOrCreate(
            ['email' => 'matheuspurgato@gmail.com'],
            [
                'name' => 'Matheus Purgato',
                'password' => Hash::make('12345678'), // Mude sua senha
            ]
        )->assignRole($masterRole); // Atribui o papel 'master'

        User::firstOrCreate(
            ['email' => 'm.purgato@interacti.com.br'],
            [
                'name' => 'Purgato InterACTI', // Nome para o usuário do Interacti
                'password' => Hash::make('12345678'), // Mude sua senha
                'account_id' => '1541936' // Pré-define o account_id
            ]
        )->assignRole($masterRole); // Atribui o papel 'master'

        Log::info("DatabaseSeeder: Usuários 'master' criados e papéis atribuídos com sucesso.");
    }
}
