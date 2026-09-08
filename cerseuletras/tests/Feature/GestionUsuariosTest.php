<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gestión de usuarios del panel, con foco en las salvaguardas: el sitio nunca
 * debe quedarse sin administradores ni permitir que alguien se autobloquee.
 *
 * Estas comprobaciones entraban por `/admin/users`. Al portarlas se vio que el
 * panel de Filament solo traía una de las tres: impedía borrarse a uno mismo
 * desde la lista —aunque no desde la pantalla de edición—, pero no impedía
 * degradarse, ni desactivarse, ni dejar el sitio sin ningún administrador
 * activo. Retirar el panel anterior sin esto habría dejado el acceso al panel
 * a un clic de perderse, sin nadie dentro capaz de deshacerlo.
 */
class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => 'admin', 'is_active' => true]);
    }

    public function test_un_admin_puede_crear_otro_usuario(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Nueva Persona',
                'email' => 'nueva@unmsm.edu.pe',
                'password' => 'contrasena-larga',
                'role' => 'admin',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $creado = User::where('email', 'nueva@unmsm.edu.pe')->first();
        $this->assertNotNull($creado);
        $this->assertSame('admin', $creado->role);
        // La contraseña se guarda hasheada, nunca en claro.
        $this->assertNotSame('contrasena-larga', $creado->password);
        $this->assertTrue(Hash::check('contrasena-larga', $creado->password));
    }

    public function test_la_contrasena_solo_cambia_si_se_escribe_una_nueva(): void
    {
        $this->actingAs($this->admin());
        $otro = $this->admin(['password' => Hash::make('original-larga')]);
        $hashPrevio = $otro->password;

        Livewire::test(EditUser::class, ['record' => $otro->getRouteKey()])
            ->fillForm(['name' => 'Nombre Cambiado', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $otro->refresh();
        $this->assertSame('Nombre Cambiado', $otro->name);
        // Sin esto, abrir una ficha y guardarla sin tocar nada dejaría a esa
        // persona fuera de su cuenta.
        $this->assertSame($hashPrevio, $otro->password);
    }

    public function test_no_puedo_quitarme_a_mi_mismo_el_acceso(): void
    {
        $admin = $this->admin();
        $this->admin(); // otro admin, para que no sea el "último"

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['role' => 'user', 'is_active' => false])
            ->call('save');

        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue((bool) $admin->is_active);
    }

    public function test_el_ultimo_admin_activo_no_puede_degradarse(): void
    {
        $unico = $this->admin();
        $otro = $this->admin();

        $this->actingAs($unico);

        // Con dos admins, degradar al segundo sí se permite.
        Livewire::test(EditUser::class, ['record' => $otro->getRouteKey()])
            ->fillForm(['role' => 'user'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('user', $otro->fresh()->role);

        // Ahora `$unico` es el único que puede entrar: ya no se degrada.
        $this->assertTrue($unico->fresh()->esUltimoAdminActivo());

        Livewire::test(EditUser::class, ['record' => $unico->getRouteKey()])
            ->fillForm(['role' => 'user'])
            ->call('save');

        $this->assertSame('admin', $unico->fresh()->role, 'El último administrador activo no debe poder degradarse.');
    }

    /**
     * Y que el botón de borrar no se ofrezca siquiera.
     *
     * Estaba resuelto en la lista pero no en la pantalla de edición, que
     * llevaba la misma acción sin ninguna condición.
     */
    public function test_no_se_ofrece_borrar_la_propia_cuenta_ni_la_del_ultimo_admin(): void
    {
        $admin = $this->admin();
        $otro = $this->admin();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertActionHidden('delete');

        // Sobre otra cuenta, con más de un admin, sí se ofrece.
        Livewire::test(EditUser::class, ['record' => $otro->getRouteKey()])
            ->assertActionVisible('delete');
    }

    public function test_una_cuenta_desactivada_pierde_el_acceso_al_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/panel')->assertSuccessful();

        $admin->update(['is_active' => false]);

        // Aun con la sesión abierta, deja de poder entrar.
        $this->assertFalse($admin->fresh()->is_active);
        $this->actingAs($admin->fresh())->get('/panel/users')->assertForbidden();
    }

    public function test_un_usuario_sin_rol_admin_no_entra_al_panel(): void
    {
        $usuario = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($usuario)->get('/panel/users')->assertForbidden();
    }
}
