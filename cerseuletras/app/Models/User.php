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
     * Quién puede entrar al panel: administrador Y cuenta activa.
     *
     * Las dos condiciones, no solo la primera. Comprobaba unicamente el rol, y
     * el middleware del panel anterior —que era el que de verdad guardaba la
     * puerta— exigia ademas que la cuenta estuviera activa. Con los dos paneles
     * conviviendo eso significaba que desactivar a alguien le cerraba `/admin`
     * y le dejaba `/panel` abierto: la baja no daba de baja.
     *
     * Filament responde 403 en vez de cerrar la sesion, como hacia el
     * middleware. Basta: lo que importa es que no entre.
     *
     * No es opcional. Sin este método Filament aplica su respaldo, que deja
     * entrar a cualquiera en local y a nadie fuera de local: las dos respuestas
     * equivocadas, y la primera además en silencio.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }

    /**
     * ¿Es la única cuenta que hoy puede entrar al panel?
     *
     * La salvaguarda vive aquí y no en el panel a propósito. Estaba metida en
     * el controlador del panel Blade, y al retirarlo se habría ido con él: el
     * panel de Filament impedía borrarse a uno mismo, pero no degradarse ni
     * desactivarse, así que quedaba a un clic dejar el sitio sin nadie capaz de
     * administrarlo — y sin nadie, tampoco hay quien lo arregle desde dentro.
     */
    public function esUltimoAdminActivo(): bool
    {
        return $this->isAdmin()
            && $this->is_active
            && self::query()->admins()->active()->where('id', '!=', $this->id)->doesntExist();
    }

    /**
     * Si el cambio que se pretende dejaría a este usuario sin acceso.
     *
     * Se pregunta con los valores que se van a guardar, no con los guardados:
     * es lo que permite frenar el cambio antes de aplicarlo.
     */
    public function perderiaElAcceso(?string $rol, bool $activa): bool
    {
        return $rol !== 'admin' || ! $activa;
    }
}
