<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'cpf',
        'guardian_cpf',
        'is_pcd',
        'phone',
        'birth_date',
        'sex',
        'city_id',
        'email_verification_code',
        'organizer_id',
        'role',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_pcd' => 'boolean',
        ];
    }

    /**
     * As inscrições do atleta — em qualquer evento, de qualquer organizador.
     *
     * Quem filtra por organizador é quem consulta (ver AthleteController): a
     * conta é da plataforma, não de um organizador. `users.organizer_id` diz
     * outra coisa — de qual organizador a pessoa é administradora.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    /** Quem tem menos disto no dia do cadastro precisa de responsável. */
    public const MAIORIDADE = 18;

    /**
     * A data de nascimento informada é de um menor de idade?
     *
     * Conta na data de hoje, não na data do evento: é no cadastro que o dado
     * do responsável é pedido.
     */
    public static function ehMenorDeIdade(?string $nascimento): bool
    {
        if (blank($nascimento)) {
            return false;
        }

        try {
            return \Illuminate\Support\Carbon::parse($nascimento)->age < self::MAIORIDADE;
        } catch (\Throwable) {
            // Data impossível é problema da regra `date`, não desta — aqui
            // ela só não pode derrubar o formulário com exceção.
            return false;
        }
    }

    public function menorDeIdade(): bool
    {
        return self::ehMenorDeIdade($this->birth_date instanceof \DateTimeInterface
            ? $this->birth_date->toDateString()
            : $this->birth_date);
    }

    /**
     * A cidade do atleta, da lista do IBGE.
     *
     * Nula em quem se cadastrou antes de 2026-09-22 — e continua nula, porque
     * não existe tela de editar perfil (ver docs/backlog.md).
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function isOrganizerAdmin(): bool
    {
        return $this->role === 'organizer_admin' && $this->organizer_id !== null;
    }
}