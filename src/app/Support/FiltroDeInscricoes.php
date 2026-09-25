<?php

namespace App\Support;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Os filtros da tela de inscrições, lidos da URL.
 *
 * Existe separado porque a tela e o PDF precisam do MESMO recorte: se a
 * tradução dos filtros vivesse no controller da tela, o relatório teria uma
 * segunda cópia — e a primeira diferença entre as duas seria um número errado
 * num papel entregue para alguém.
 *
 * Ver docs/specs/gestao-de-inscricoes.md.
 */
final class FiltroDeInscricoes
{
    public const SITUACOES = [
        'pagas' => Subscription::PAGA,
        'pendentes' => Subscription::PENDENTE,
        'canceladas' => Subscription::CANCELADA,
    ];

    public function __construct(
        public readonly ?int $evento,
        public readonly ?string $situacao,
        public readonly bool $comDesconto,
        public readonly string $busca,
        public readonly bool $soPcd = false,
    ) {
    }

    public static function daRequisicao(Request $request): self
    {
        $situacao = $request->query('situacao');

        return new self(
            evento: ((int) $request->query('evento')) ?: null,
            // Valor desconhecido na URL é tratado como "todas": filtro é
            // conveniência, não deve virar erro na cara de quem usa.
            situacao: array_key_exists($situacao, self::SITUACOES) ? $situacao : null,
            comDesconto: $request->boolean('desconto'),
            busca: trim((string) $request->query('busca')),
            soPcd: $request->boolean('pcd'),
        );
    }

    public function aplicar(Builder $query): Builder
    {
        return $query
            ->when($this->evento, fn ($q) => $q->where('subscriptions.event_id', $this->evento))
            ->when($this->situacao, fn ($q) => $q->where('subscriptions.status', self::SITUACOES[$this->situacao]))
            ->when($this->comDesconto, fn ($q) => $q->where('subscriptions.discount_amount', '>', 0))
            ->when($this->soPcd, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('is_pcd', true)))
            ->when($this->busca !== '', function ($q) {
                // Nome, e-mail ou CPF: quem procura um inscrito tem um desses
                // três na mão. O CPF é comparado só pelos dígitos, porque no
                // banco ele está sem pontuação e a pessoa digita com.
                $termo = '%' . $this->busca . '%';
                $digitos = preg_replace('/\D/', '', $this->busca);

                $q->whereHas('user', function ($u) use ($termo, $digitos) {
                    $u->where('name', 'like', $termo)
                        ->orWhere('email', 'like', $termo)
                        ->when($digitos !== '', fn ($c) => $c->orWhere('cpf', 'like', "%{$digitos}%"));
                });
            });
    }

    public function ativo(): bool
    {
        return $this->evento !== null
            || $this->situacao !== null
            || $this->comDesconto
            || $this->soPcd
            || $this->busca !== '';
    }

    /** Os filtros em português, para o cabeçalho do relatório. */
    public function descricao(): string
    {
        $partes = [];

        if ($this->situacao) {
            $partes[] = $this->situacao;
        }

        if ($this->comDesconto) {
            $partes[] = 'com desconto';
        }

        if ($this->soPcd) {
            $partes[] = 'só PCD';
        }

        if ($this->busca !== '') {
            $partes[] = "busca por \"{$this->busca}\"";
        }

        return $partes ? implode(', ', $partes) : 'todas as inscrições';
    }

    /** O que vai na URL, para manter o filtro entre a tela e o relatório. */
    public function paraUrl(): array
    {
        return array_filter([
            'evento' => $this->evento,
            'situacao' => $this->situacao,
            'desconto' => $this->comDesconto ? 1 : null,
            'pcd' => $this->soPcd ? 1 : null,
            'busca' => $this->busca !== '' ? $this->busca : null,
        ]);
    }
}
