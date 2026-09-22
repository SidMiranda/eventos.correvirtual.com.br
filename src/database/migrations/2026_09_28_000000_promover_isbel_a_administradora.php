<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dá acesso ao painel a uma atleta já cadastrada (pedido do dono, 2026-09-22).
 *
 * Normalmente isto é `php artisan admin:criar {email}`, que já promove usuário
 * existente. Só que o comando precisa ser rodado NO servidor, e o pedido veio
 * sem o e-mail dela — daí a busca pelo nome aqui.
 *
 * Promover alguém a administrador dá acesso a CPF, e-mail e telefone de todos
 * os inscritos, e ao cadastro de eventos e preços. Por isso a trava: só age se
 * houver **exatamente uma** pessoa com esse nome. Duas Isbel, ou nenhuma, e a
 * migration não faz nada — melhor não promover do que promover a errada.
 */
return new class extends Migration
{
    private const NOME = 'isbel domingos';

    public function up(): void
    {
        $organizador = DB::table('organizers')
            ->where('domain', 'eventos.correvirtual.com.br')
            ->first();

        if (! $organizador) {
            echo "  [isbel] Organizador não encontrado neste banco; nada a fazer.\n";

            return;
        }

        // A comparação é feita em PHP porque normalizar acento no SQL não é
        // portável entre MySQL e SQLite — e "Isbel Domingos", "isbel domingos"
        // e "Ísbel  Domingos" são a mesma pessoa.
        $candidatas = DB::table('users')
            ->select('id', 'name', 'role', 'email', 'email_verified_at')
            ->get()
            ->filter(fn ($u) => $this->normalizar($u->name) === self::NOME);

        if ($candidatas->count() !== 1) {
            echo "  [isbel] {$candidatas->count()} pessoa(s) com esse nome; nada foi alterado.\n";

            return;
        }

        $usuaria = $candidatas->first();

        DB::table('users')->where('id', $usuaria->id)->update([
            'role' => 'organizer_admin',
            'organizer_id' => $organizador->id,
            'active' => true,
            // Sem e-mail confirmado o login barra a entrada (LoginController),
            // e ela entraria no painel só para levar um erro.
            'email_verified_at' => $usuaria->email_verified_at ?? now(),
            'updated_at' => now(),
        ]);

        echo "  [isbel] {$usuaria->name} <{$usuaria->email}> agora administra \"{$organizador->name}\".\n";
    }

    public function down(): void
    {
        // Sem volta automática: desfazer teria que adivinhar o papel anterior,
        // e tirar acesso de quem já está usando o painel é pior que deixar.
        // Para reverter na mão: role = 'athlete', organizer_id = null.
    }

    private function normalizar(?string $nome): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', Str::ascii((string) $nome))));
    }
};
