<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Zera o USO do sistema e mantém o CATÁLOGO.
 *
 * O sistema vai ao ar de verdade na segunda-feira seguinte a 2026-09-20, e
 * tudo que a produção tem de inscrição e pagamento até lá foi teste — Pix
 * gerado, pago ou abandonado, não importa. O que o organizador cadastrou
 * (eventos, modalidades, kits, equipes, patrocinadores, cupons) e as contas
 * de usuário ficam; o rastro de uso sai, e os contadores que esse uso
 * inflou voltam a zero.
 *
 * Diferente de base:limpar-testes, NÃO apaga evento nenhum.
 *
 * Simula por padrão. Só apaga com --force, e dentro de uma transação. Feito
 * para rodar pelo workflow "Zerar uso em produção", que antes disso tira um
 * backup na VPS e uma cópia no R2 — não existe caminho de apagar sem backup.
 */
class ZerarUsoDeProducao extends Command
{
    protected $signature = 'base:zerar-uso {--force : Apaga de verdade (sem isto, só mostra o que sairia)}';

    protected $description = 'Apaga inscrições, pagamentos e tokens e zera os contadores de uso, mantendo catálogo e usuários';

    /** O que sai, na ordem em que precisa sair (pagamento aponta para inscrição). */
    private const TABELAS_DE_USO = [
        'payments' => 'pagamentos',
        'subscriptions' => 'inscrições',
        'personal_access_tokens' => 'tokens de API',
        'password_reset_tokens' => 'tokens de troca de senha',
        'failed_jobs' => 'jobs com falha',
        'jobs' => 'jobs na fila',
    ];

    /** O que fica — listado só para o log mostrar que continuou igual. */
    private const TABELAS_DE_CATALOGO = [
        'users' => 'usuários',
        'organizers' => 'organizadores',
        'events' => 'eventos',
        'event_modalities' => 'modalidades',
        'event_kits' => 'kits',
        'teams' => 'equipes',
        'sponsors' => 'patrocinadores',
        'coupons' => 'cupons',
    ];

    public function handle(): int
    {
        $this->info('Banco: ' . DB::connection()->getDatabaseName());
        $this->newLine();

        $this->listarInscricoes();

        $this->info('== Sai ==');
        $this->contagens(self::TABELAS_DE_USO);
        $this->line(sprintf('  %-34s %d', 'cupons com uso a zerar', DB::table('coupons')->where('used_quantity', '>', 0)->count()));
        $this->line(sprintf('  %-34s %d', 'kits com vendas a zerar', DB::table('event_kits')->where('sold', '>', 0)->count()));
        $this->line(sprintf('  %-34s %d', 'modalidades com inscritos a zerar', DB::table('event_modalities')->where('registered_count', '>', 0)->count()));

        $this->newLine();
        $this->info('== Fica ==');
        $this->contagens(self::TABELAS_DE_CATALOGO);
        $this->newLine();

        if (! $this->option('force')) {
            $this->warn('Simulação. Nada foi apagado — repita com --force.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            foreach (array_keys(self::TABELAS_DE_USO) as $tabela) {
                DB::table($tabela)->delete();
            }

            // Os contadores foram inflados por inscrições que não existem
            // mais. "Usado 3 vezes" num cupom sem inscrição nenhuma é mentira.
            DB::table('coupons')->update(['used_quantity' => 0]);
            DB::table('event_kits')->update(['sold' => 0]);
            DB::table('event_modalities')->update(['registered_count' => 0]);
        });

        $this->info('Feito.');
        $this->newLine();

        $this->info('== Depois ==');
        $this->contagens(self::TABELAS_DE_USO);
        $this->contagens(self::TABELAS_DE_CATALOGO);

        return self::SUCCESS;
    }

    /** Cada inscrição que vai sair, com dono e evento: é o "revisar antes". */
    private function listarInscricoes(): void
    {
        $inscricoes = DB::table('subscriptions')
            ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('events', 'events.id', '=', 'subscriptions.event_id')
            ->orderBy('subscriptions.id')
            ->get([
                'subscriptions.id', 'subscriptions.user_id', 'users.email', 'subscriptions.event_id',
                'events.title', 'subscriptions.status', 'subscriptions.price', 'subscriptions.created_at',
            ]);

        $this->info('== Inscrições que saem ==');

        if ($inscricoes->isEmpty()) {
            $this->line('  (nenhuma)');
        }

        foreach ($inscricoes as $s) {
            $this->line(sprintf(
                '  #%d %s | evento #%s %s | %s | R$ %s | pagamentos=%d | %s',
                $s->id,
                $s->email ?? "usuário #{$s->user_id} NÃO EXISTE",
                $s->event_id,
                $s->title ?? 'NÃO EXISTE MAIS',
                $s->status,
                $s->price,
                DB::table('payments')->where('subscription_id', $s->id)->count(),
                substr((string) $s->created_at, 0, 10),
            ));
        }

        $this->newLine();
    }

    private function contagens(array $tabelas): void
    {
        foreach ($tabelas as $tabela => $rotulo) {
            $this->line(sprintf('  %-34s %d', $rotulo, DB::table($tabela)->count()));
        }
    }
}
