<?php

/**
 * Middleware utilizado para acesso ao chat
 * via parâmetros de email e account_id, criando
 * usuário e sala automaticamente.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;

class ChatraceAutoLogin
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->query('email');
        $accountId = $request->query('account_id');

        if ($email === '{{Email}}' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(400, 'O parâmetro de email não foi preenchido corretamente. O email recebido foi: ' . $email . ' e o account_id foi: ' . $accountId);
        }
        if (!$email || !$accountId) {
            abort(403, 'Missing email or account_id');
        }

        // Limpa tudo ANTES de logar
        session()->flush();
        session()->regenerate();

        // Cria ou busca o user e loga
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $email, 'password' => bcrypt(str()->random(16))]
        );
        Auth::login($user);
        // usando Passport
        $token = auth('api')->login($user); // JWT
        // Cria ou busca a sala
        $room = Room::firstOrCreate(
            ['slug' => 'sala-' . $accountId],
            [
                'name' => 'Espaço #' . $accountId,
                'description' => 'Sala criada automaticamente para account_id ' . $accountId,
                'is_private' => true,
                'created_by' => $user->id,
            ]
        );

        // Garante que o user está vinculado
        if (!$room->users()->where('user_id', $user->id)->exists()) {
            $room->users()->attach($user->id, ['joined_at' => now()]);
        }

        // Redireciona com headers anti-cache
        return redirect()
            ->to(route('rooms.show', $room->slug) . "?token={$token}")
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
