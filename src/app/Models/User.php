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
        'phone',
        'birth_date',
        'sex',
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

    public function isOrganizerAdmin(): bool
    {
        return $this->role === 'organizer_admin' && $this->organizer_id !== null;
    }
}