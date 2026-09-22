<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'title',
        'slug',
        'description',
        'schedule',
        'location',
        'event_date',
        'registration_deadline',
        'banner_url',
        'banner_ratio',
        'accent_color',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'registration_deadline' => 'datetime',
            'banner_ratio' => 'float',
        ];
    }

    public function kits() {
        return $this->hasMany(EventKit::class);
    }

    public function modalities() {
        return $this->hasMany(EventModality::class);
    }

    public function organizer() {
        return $this->belongsTo(Organizer::class);
    }

    public function subscriptions() {
        return $this->hasMany(Subscription::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Aparência
    |--------------------------------------------------------------------------
    */

    /** Azul escuro do tema do site, usado quando o evento não define cor. */
    public const COR_PADRAO = '#0d1b2a';

    /**
     * O topo da página deve mostrar o banner enviado?
     *
     * Só quando existe imagem E ela é larga: cartaz retrato num quadro de
     * 320px de altura fica recortado no nome e na data — foi o que motivou o
     * degradê fixo em 2026-08-30. Ver docs/specs/frontend-publico.md.
     */
    public function temBannerParaOTopo(): bool
    {
        return (bool) $this->banner_url
            && $this->banner_ratio !== null
            && $this->banner_ratio >= \App\Support\ImagensDoEvento::PROPORCAO_DE_BANNER;
    }

    /**
     * A proporção para o quadro do topo, limitada.
     *
     * O teto existe porque um banner de 2:1 numa tela de 1400px daria 700px de
     * altura — o evento inteiro empurrado para fora da primeira tela. Acima do
     * teto o quadro para de crescer e a imagem preenche cortando de leve.
     */
    public function proporcaoDoBanner(): float
    {
        return max(3.4, (float) $this->banner_ratio);
    }

    public function corDeDestaque(): string
    {
        return $this->accent_color ?: self::COR_PADRAO;
    }

    /**
     * O degradê que substitui a imagem quando o evento não tem arte enviada.
     *
     * Sempre escuro: o nome do evento vai por cima em branco, e precisa ser
     * legível independentemente da cor escolhida. A cor do evento entra só como
     * um puxão no meio do degradê, não como fundo chapado.
     */
    public function degrade(): string
    {
        $cor = $this->corDeDestaque();

        return "linear-gradient(135deg, #05080d 0%, {$cor} 58%, #05080d 100%)";
    }

    /*
    |--------------------------------------------------------------------------
    | Situação
    |--------------------------------------------------------------------------
    | Deduzida das datas, sem coluna própria (decisão do dono em 2026-08-29):
    | com poucos eventos, uma coluna a mais seria só mais um lugar para
    | desatualizar. A contrapartida é que não dá para cancelar nem reabrir um
    | evento na mão — se isso for preciso, vira coluna.
    */

    public function jaAconteceu(): bool
    {
        return $this->event_date !== null && $this->event_date->isPast();
    }

    public function inscricoesAbertas(): bool
    {
        return $this->active
            && !$this->jaAconteceu()
            && $this->registration_deadline !== null
            && $this->registration_deadline->isFuture();
    }

    /** Rótulo curto para as telas. */
    public function situacao(): string
    {
        if (!$this->active) {
            return 'Inativo';
        }

        if ($this->jaAconteceu()) {
            return 'Realizado';
        }

        return $this->inscricoesAbertas() ? 'Inscrições abertas' : 'Inscrições encerradas';
    }

    /** Cor da etiqueta, seguindo as classes do template do painel. */
    public function corDaSituacao(): string
    {
        return match ($this->situacao()) {
            'Inscrições abertas' => 'success',
            'Inscrições encerradas' => 'warning',
            'Realizado' => 'secondary',
            default => 'danger',
        };
    }
}