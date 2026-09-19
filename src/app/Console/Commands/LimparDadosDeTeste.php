<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tira da base o que sobrou da fase de demonstração.
 *
 * Até 2026-08-30 nada no banco era prova real: os eventos vinham de seeder, os
 * kits eram todos de R$ 0,05 e as inscrições foram feitas por quem estava
 * testando a plataforma. Esse resto atrapalhava de verdade — aparecia em
 * "minhas inscrições" como se fosse compromisso do atleta, e apontava para
 * evento que já tinha saído do site.
 *
 * O que fica: atletas, os eventos reais do organizador e as modalidades e kits
 * desses. O evento de teste do fluxo (a R$ 0,05 de propósito) também fica, a
 * não ser que seja apontado em --evento-de-teste — decisão do dono em
 * 2026-09-20, ao deixar a produção limpa antes das provas reais.
 *
 * Simula por padrão. Só apaga com --force, e dentro de uma transação: ou some
 * tudo, ou não some nada. Feito comando e não SQL solto porque precisava rodar
 * em dois bancos (dev e produção) e porque, escrito assim, dá para revisar
 * antes e para conferir depois. --listar é o "revisar antes": imprime cada
 * evento, cada inscrição com dono, cupons e órfãos, para ler no log do
 * workflow "Comando em produção" sem precisar de SSH.
 */
class LimparDadosDeTeste extends Command
{
    protected $signature = 'base:limpar-testes
        {--force : Apaga de verdade (sem isto, só mostra o que sairia)}
        {--listar : Só imprime o que existe no banco, linha a linha, e sai}
        {--evento-de-teste= : Slug do evento de teste do fluxo, para apagá-lo junto (com kits, modalidades e cupons)}';

    protected $description = 'Remove os eventos mocados e todo o histórico de inscrição e pagamento da fase de teste';

    /**
     * Identificados por slug, não por id: os ids são diferentes em dev e em
     * produção, e um número errado aqui apagaria a prova errada.
     */
    private const EVENTOS_MOCADOS = [
        'carnarun-do-quarteto-2025',
        'primeira-corre-que-a-bruxa-vem-ai',
        'desafio-virtual-pastelaria-pastelicia-2025',
        '98-corrida-internacional-de-sao-silvestre',
        '28-maratona-internacional-de-sao-paulo',
        'night-run-etapa-fogo-sp',
    ];

    public function handle(): int
    {
        $this->info('Banco: ' . DB::connection()->getDatabaseName());
        $this->newLine();

        if ($this->option('listar')) {
            $this->listar();

            return self::SUCCESS;
        }

        $slugs = self::EVENTOS_MOCADOS;

        if ($slugDeTeste = $this->option('evento-de-teste')) {
            if (! DB::table('events')->where('slug', $slugDeTeste)->exists()) {
                $this->error("Nenhum evento com o slug \"{$slugDeTeste}\". Confira com --listar.");

                return self::FAILURE;
            }

            $slugs[] = $slugDeTeste;
        }

        $ids = DB::table('events')->whereIn('slug', $slugs)->pluck('id');

        $contas = [
            'pagamentos' => DB::table('payments')->count(),
            'inscrições' => DB::table('subscriptions')->count(),
            'cupons dos eventos a apagar' => DB::table('coupons')->whereIn('event_id', $ids)->count(),
            'kits dos eventos a apagar' => DB::table('event_kits')->whereIn('event_id', $ids)->count(),
            'modalidades dos eventos a apagar' => DB::table('event_modalities')->whereIn('event_id', $ids)->count(),
            'eventos a apagar' => $ids->count(),
        ];

        foreach ($contas as $rotulo => $quantidade) {
            $this->line(sprintf('  %-34s %d', $rotulo, $quantidade));
        }

        if ($ids->isNotEmpty()) {
            $this->newLine();
            $this->line('  Eventos que saem:');

            foreach (DB::table('events')->whereIn('id', $ids)->orderBy('event_date')->get(['id', 'title', 'slug']) as $evento) {
                $this->line("    #{$evento->id} {$evento->title} ({$evento->slug})");
            }
        }

        $this->newLine();

        if (! $this->option('force')) {
            $this->warn('Simulação. Nada foi apagado — repita com --force.');

            return self::SUCCESS;
        }

        // Transação porque a ordem importa: pagamento aponta para inscrição,
        // inscrição aponta para kit, modalidade, evento e cupom; cupom aponta
        // para evento. Uma falha no meio deixaria referência apontando para
        // linha que não existe mais.
        DB::transaction(function () use ($ids) {
            DB::table('payments')->delete();
            DB::table('subscriptions')->delete();
            DB::table('coupons')->whereIn('event_id', $ids)->delete();
            DB::table('event_kits')->whereIn('event_id', $ids)->delete();
            DB::table('event_modalities')->whereIn('event_id', $ids)->delete();
            DB::table('events')->whereIn('id', $ids)->delete();
        });

        $this->info('Feito.');

        $this->line(sprintf(
            '  Restaram %d eventos, %d modalidades, %d kits, %d cupons, %d inscrições, %d pagamentos e %d usuários.',
            DB::table('events')->count(),
            DB::table('event_modalities')->count(),
            DB::table('event_kits')->count(),
            DB::table('coupons')->count(),
            DB::table('subscriptions')->count(),
            DB::table('payments')->count(),
            DB::table('users')->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * O retrato do banco, linha a linha, para decidir o que apagar.
     *
     * Marca o que está órfão (inscrição de evento ou usuário que não existe
     * mais) — é o que o dono pediu para limpar "de todo mundo".
     */
    private function listar(): void
    {
        $this->info('== Usuários ==');

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email', 'role', 'organizer_id']) as $u) {
            $this->line(sprintf('  #%d %s <%s> %s%s', $u->id, $u->name, $u->email, $u->role, $u->organizer_id ? " org={$u->organizer_id}" : ''));
        }

        $this->newLine();
        $this->info('== Eventos ==');

        foreach (DB::table('events')->orderBy('event_date')->get() as $e) {
            $this->line(sprintf(
                '  #%d %s | slug=%s | data=%s | %s | inscrições=%d (pagas %d) | kits=%d | modalidades=%d | cupons=%d',
                $e->id,
                $e->title,
                $e->slug,
                substr((string) $e->event_date, 0, 10),
                $e->active ? 'ativo' : 'INATIVO',
                DB::table('subscriptions')->where('event_id', $e->id)->count(),
                DB::table('subscriptions')->where('event_id', $e->id)->where('status', 'paid')->count(),
                DB::table('event_kits')->where('event_id', $e->id)->count(),
                DB::table('event_modalities')->where('event_id', $e->id)->count(),
                DB::table('coupons')->where('event_id', $e->id)->count(),
            ));
        }

        $this->newLine();
        $this->info('== Inscrições ==');

        $inscricoes = DB::table('subscriptions')
            ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('events', 'events.id', '=', 'subscriptions.event_id')
            ->orderBy('subscriptions.id')
            ->get([
                'subscriptions.id', 'subscriptions.user_id', 'users.email', 'subscriptions.event_id',
                'events.title', 'subscriptions.status', 'subscriptions.price', 'subscriptions.created_at',
            ]);

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
        $this->info('== Cupons ==');

        $cupons = DB::table('coupons')->orderBy('id')->get();

        if ($cupons->isEmpty()) {
            $this->line('  (nenhum)');
        }

        foreach ($cupons as $c) {
            $this->line(sprintf(
                '  #%d %s | evento #%d | %s %s | usos %d/%d | %s | expira %s',
                $c->id, $c->code, $c->event_id, $c->discount_type, $c->discount_value,
                $c->used_quantity, $c->total_quantity, $c->active ? 'ativo' : 'inativo', $c->expires_at,
            ));
        }

        $this->newLine();
        $this->info('== Totais ==');
        $this->line(sprintf(
            '  usuários=%d eventos=%d inscrições=%d pagamentos=%d cupons=%d equipes=%d patrocinadores=%d',
            DB::table('users')->count(),
            DB::table('events')->count(),
            DB::table('subscriptions')->count(),
            DB::table('payments')->count(),
            DB::table('coupons')->count(),
            DB::table('teams')->count(),
            DB::table('sponsors')->count(),
        ));
        $this->line(sprintf('  órfãos: inscrições sem evento=%d',
            DB::table('subscriptions')->whereNotIn('event_id', DB::table('events')->select('id'))->count()));
        $this->line(sprintf('  órfãos: inscrições sem usuário=%d',
            DB::table('subscriptions')->whereNotIn('user_id', DB::table('users')->select('id'))->count()));
        $this->line(sprintf('  órfãos: pagamentos sem inscrição=%d',
            DB::table('payments')->whereNotIn('subscription_id', DB::table('subscriptions')->select('id'))->count()));
    }
}
