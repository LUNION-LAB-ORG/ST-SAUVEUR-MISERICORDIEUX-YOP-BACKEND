<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'service_id' => 'int',
        'last_login_at' => 'datetime',
    ];

    /** Rôles du back-office (admin passe partout). */
    public const ROLES = ['admin', 'priest', 'secretariat', 'communication', 'treasurer', 'movement_leader'];

    /** Statuts qui bloquent la connexion (« inactive » ; « disabled » toléré comme synonyme). */
    public const DISABLED_STATUSES = ['inactive', 'disabled'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Vrai si l'utilisateur a l'un des rôles donnés (l'admin a tous les rôles). */
    public function hasRole(string ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }

    public function isDisabled(): bool
    {
        return in_array($this->status, self::DISABLED_STATUSES, true);
    }

    /** Nom affiché (fullname, sinon name). */
    public function displayName(): ?string
    {
        return $this->fullname ?: $this->name;
    }

    /** Mouvement géré par un responsable de mouvement. */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

	public function time_slots()
	{
		return $this->hasMany(TimeSlot::class, 'priest_id');
	}
}
