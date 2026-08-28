<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Valores por defecto en memoria, iguales a los de la tabla.
     *
     * Sin esto, un usuario recién creado sin indicar `is_active` queda con
     * `null` en la instancia aunque en la base de datos se guarde `true`, y
     * cualquier comprobación sobre ese objeto lo trataría como inactivo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include admin users.
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Quién puede entrar al panel de Filament.
     *
     * Es el mismo criterio que aplica el middleware `isAdmin` a las rutas del
     * panel de siempre: un solo sitio decide, y los dos paneles coinciden
     * mientras conviven.
     *
     * No es opcional. Sin este método Filament aplica su respaldo, que deja
     * entrar a cualquiera en local y a nadie fuera de local: las dos respuestas
     * equivocadas, y la primera además en silencio.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
